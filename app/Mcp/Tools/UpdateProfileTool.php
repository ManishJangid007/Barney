<?php

namespace App\Mcp\Tools;

use App\Models\PersonalProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateProfileTool extends Tool
{
    protected string $name = 'update_profile';

    protected string $description = <<<'MARKDOWN'
        Create or update the user's personal profile.
        Pass only the fields you want to change. Unspecified fields remain unchanged.
        If no profile exists yet, a new one is created (first_name, last_name, and date_of_birth are required for creation).
        Returns the full updated profile.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $fields = [
            'first_name', 'last_name', 'date_of_birth', 'email',
            'phone', 'address', 'city', 'state', 'country', 'pin_code', 'notes',
        ];

        $data = array_filter(
            $request->all(),
            fn ($value, $key) => in_array($key, $fields) && $value !== null,
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($data)) {
            return Response::error('No valid fields provided. Accepted fields: ' . implode(', ', $fields));
        }

        $profile = PersonalProfile::first();

        if (! $profile) {
            $required = ['first_name', 'last_name', 'date_of_birth'];
            $missing = array_diff($required, array_keys($data));

            if (! empty($missing)) {
                return Response::error('Creating a new profile requires: ' . implode(', ', $missing));
            }

            $profile = PersonalProfile::create($data);
        } else {
            $profile->update($data);
        }

        return Response::text(json_encode([
            'message' => 'Profile updated',
            'profile' => $profile->fresh()->toArray(),
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'first_name' => $schema->string()->description('First name'),
            'last_name' => $schema->string()->description('Last name'),
            'date_of_birth' => $schema->string()->description('Date of birth in YYYY-MM-DD format'),
            'email' => $schema->string()->nullable()->description('Email address'),
            'phone' => $schema->string()->nullable()->description('Phone number'),
            'address' => $schema->string()->nullable()->description('Full address'),
            'city' => $schema->string()->nullable()->description('City'),
            'state' => $schema->string()->nullable()->description('State'),
            'country' => $schema->string()->nullable()->description('Country, defaults to India'),
            'pin_code' => $schema->string()->nullable()->description('PIN code / ZIP code'),
            'notes' => $schema->string()->nullable()->description('Any personal notes'),
        ];
    }
}
