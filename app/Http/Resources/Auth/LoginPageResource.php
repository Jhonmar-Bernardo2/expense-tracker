<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'canResetPassword' => (bool) $this['can_reset_password'],
            'canRegister' => (bool) $this['can_register'],
            'status' => $this['status'],
        ];
    }
}
