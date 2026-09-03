<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait ValidationTrait
{
    protected function validateOrFail(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        return $request->validate($rules, $messages, $attributes);
    }
}
