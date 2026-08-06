<?php

namespace App\Services\Wallet;

use Illuminate\Http\UploadedFile;

class StoresWalletDepositProofImage
{
    public function store(UploadedFile $proofImage): string
    {
        return $proofImage->store('wallet-deposit-proofs', 'public');
    }
}
