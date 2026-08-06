<?php

namespace App\Http\Middleware;

use App\Http\Responses\Concerns\RespondsWithApiEnvelope;
use App\Models\User;
use App\Services\Auth\ResolvesUserAccountAccessRestrictionMessage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberAccountIsNotRestricted
{
    use RespondsWithApiEnvelope;

    public function __construct(
        private readonly ResolvesUserAccountAccessRestrictionMessage $resolvesUserAccountAccessRestrictionMessage,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::guard('api')->user();

        if ($user === null) {
            return $next($request);
        }

        $restrictionMessage = $this->resolvesUserAccountAccessRestrictionMessage
            ->restrictionMessageOrNull($user);

        if ($restrictionMessage !== null) {
            Auth::guard('api')->logout();

            return $this->errorResponse(
                message: $restrictionMessage,
                statusCode: 403,
            );
        }

        return $next($request);
    }
}
