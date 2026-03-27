<?php

namespace App\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecurityPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'canManageTwoFactor' => (bool) $this['can_manage_two_factor'],
            'twoFactorEnabled' => (bool) ($this['two_factor_enabled'] ?? false),
            'requiresConfirmation' => (bool) ($this['requires_confirmation'] ?? false),
        ];
    }
}
