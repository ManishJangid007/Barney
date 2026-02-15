<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ConfirmDeleteTool;
use App\Mcp\Tools\GetPreferencesTool;
use App\Mcp\Tools\GetProfileTool;
use App\Mcp\Tools\GetSummaryTool;
use App\Mcp\Tools\ListAccountsTool;
use App\Mcp\Tools\ListExpensesTool;
use App\Mcp\Tools\ListIncomesTool;
use App\Mcp\Tools\ListTransfersTool;
use App\Mcp\Tools\LogExpenseTool;
use App\Mcp\Tools\LogIncomeTool;
use App\Mcp\Tools\ManageAccountTool;
use App\Mcp\Tools\ManagePreferenceTool;
use App\Mcp\Tools\RequestDeleteTool;
use App\Mcp\Tools\TransferFundsTool;
use App\Mcp\Tools\UpdateExpenseTool;
use App\Mcp\Tools\UpdateIncomeTool;
use App\Mcp\Tools\UpdateProfileTool;
use Laravel\Mcp\Server;

class BarneyServer extends Server
{
    protected string $name = 'Barney';

    protected string $version = '0.0.1';

    protected string $instructions = <<<'MARKDOWN'
        I'm Barney, your personal finance data assistant.

        ## How I Work
        - ALWAYS call get_preferences before any finance action to respect your rules and defaults.
        - Use list_accounts to look up account IDs before logging expenses, incomes, or transfers.
        - NEVER delete records directly. Always use request_delete → show details to user → confirm_delete after explicit confirmation.
        - Responses should be short and concise.

        ## Tool Workflow
        1. get_preferences → check user rules (default accounts, categorization, etc.)
        2. For reads → list/get tools
        3. For writes → log/manage/transfer tools
        4. For deletes → request_delete → user confirms → confirm_delete
        5. For overview → get_summary
    MARKDOWN;

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Profile & Preferences
        GetProfileTool::class,
        UpdateProfileTool::class,
        GetPreferencesTool::class,
        ManagePreferenceTool::class,

        // Accounts
        ListAccountsTool::class,
        ManageAccountTool::class,

        // Expenses (outward)
        LogExpenseTool::class,
        UpdateExpenseTool::class,
        ListExpensesTool::class,

        // Incomes (inward)
        LogIncomeTool::class,
        UpdateIncomeTool::class,
        ListIncomesTool::class,

        // Transfers
        TransferFundsTool::class,
        ListTransfersTool::class,

        // Delete safety wall
        RequestDeleteTool::class,
        ConfirmDeleteTool::class,

        // Dashboard
        GetSummaryTool::class,
    ];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
