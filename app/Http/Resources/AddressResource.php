<?php

namespace App\Http\Resources;


use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            "id"              => $this->id,
            "user_id"         => $this->user_id,
            "country"         => $this->country,
            "label"           => $this->label,
            "governorate"     => $this->governorate,
            "city"            => $this->city,
            "street"          => $this->street,
            "building_number" => $this->building_number,
            "phone"           => $this->phone,
            "address"         => $this->address,
            "apartment"       => $this->apartment,
            "latitude"        => $this->latitude,
            "longitude"       => $this->longitude,
        ];
    }
}
