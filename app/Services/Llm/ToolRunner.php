<?php

namespace App\Services\Llm;

use App\Mcp\Servers\BarneyServer;
use Laravel\Mcp\Request as McpRequest;

class ToolRunner
{
    /** @var array<string, class-string> name => class mapping */
    protected array $toolMap = [];

    public function __construct()
    {
        foreach (BarneyServer::getToolClasses() as $toolClass) {
            $tool = new $toolClass;
            $name = $tool->toArray()['name'];
            $this->toolMap[$name] = $toolClass;
        }
    }

    /**
     * Build OpenAI function-calling tool definitions from BarneyServer's registered tools.
     */
    public function buildToolDefinitions(): array
    {
        $definitions = [];

        foreach (BarneyServer::getToolClasses() as $toolClass) {
            $tool = new $toolClass;
            $schema = $tool->toArray();

            $properties = $schema['inputSchema']['properties'] ?? new \stdClass();
            $required = $schema['inputSchema']['required'] ?? [];

            $definitions[] = [
                'type' => 'function',
                'function' => [
                    'name' => $schema['name'],
                    'description' => $schema['description'] ?? '',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => empty((array) $properties) ? new \stdClass() : $properties,
                        'required' => $required,
                    ],
                ],
            ];
        }

        return $definitions;
    }

    /**
     * Execute an MCP tool internally by name.
     *
     * Returns the tool's text response as a string.
     */
    public function executeTool(string $name, array $arguments): string
    {
        if (! isset($this->toolMap[$name])) {
            return json_encode(['error' => "Unknown tool: {$name}"]);
        }

        try {
            $toolClass = $this->toolMap[$name];
            $tool = new $toolClass;
            $request = new McpRequest($arguments);
            $response = $tool->handle($request);

            return (string) $response->content();
        } catch (\Throwable $e) {
            return json_encode(['error' => "Tool execution failed: {$e->getMessage()}"]);
        }
    }
}
