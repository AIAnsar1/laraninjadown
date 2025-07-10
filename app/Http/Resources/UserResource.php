<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        parent::toArray($request);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'date' => $this->date,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at,
            'phone' => $this->phone,
            'phone_verified' => $this->phone_verified_at,
            'status' => $this->status,
            'address' => $this->address,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
