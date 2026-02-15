<?php

namespace App\Mcp\Tools;

use App\Models\PersonalProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetProfileTool extends Tool
{
    protected string $name = 'get_profile';

    protected string $description = <<<'MARKDOWN'
        Retrieve the user's personal profile information.
        This returns a single profile row containing name, date of birth, contact details, and address.
        There is only one user profile in the system.
        Returns an error if the profile has not been created yet.
        Use update_profile to create or modify the profile.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $profile = PersonalProfile::first();

        if (! $profile) {
            return Response::error('No profile found. Use update_profile to create one.');
        }

        return Response::text(json_encode($profile->toArray(), JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
