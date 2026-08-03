<?php

namespace App\Http\Resources;

use App\Enums\InvestmentPackageAvailabilityStatus;
use App\Enums\InvestmentPackageRiskLevel;
use App\Models\InvestmentPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvestmentPackage
 */
class InvestmentPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $riskLevel = (string) ($this->risk_level ?? '');
        $availabilityStatus = (string) ($this->availability_status ?? '');
        $effectiveAvailabilityStatus = $this->effectiveAvailabilityStatus();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_pitch' => $this->short_pitch,
            'description' => $this->description,
            'expected_return_percent' => $this->expected_return_percent,
            'term_days' => $this->term_days,
            'minimum_amount_usd' => $this->minimum_amount_usd,
            'maximum_amount_usd' => $this->maximum_amount_usd,
            'max_participants' => $this->max_participants,
            'joined_count' => $this->joined_count,
            'risk_level' => $riskLevel,
            'risk_level_label' => $this->resolveRiskLevelLabel($riskLevel),
            'availability_status' => $availabilityStatus,
            'availability_status_label' => $this->resolveAvailabilityStatusLabel($availabilityStatus),
            'effective_availability_status' => $effectiveAvailabilityStatus,
            'effective_availability_status_label' => $this->resolveAvailabilityStatusLabel($effectiveAvailabilityStatus),
            'expires_at' => $this->expires_at,
            'is_featured' => (bool) $this->is_featured,
            'highlights' => $this->highlights ?? [],
            'remaining_seats' => $this->remainingSeats(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveRiskLevelLabel(string $riskLevel): string
    {
        return InvestmentPackageRiskLevel::tryFrom($riskLevel)?->label()
            ?? ucfirst(str_replace('_', ' ', $riskLevel));
    }

    private function resolveAvailabilityStatusLabel(string $availabilityStatus): string
    {
        return InvestmentPackageAvailabilityStatus::tryFrom($availabilityStatus)?->label()
            ?? ucfirst(str_replace('_', ' ', $availabilityStatus));
    }
}
