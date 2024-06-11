<?php

namespace App\Commands\Internal;

use Throwable;

use function getenv;
use function implode;
use function sprintf;
use function array_map;
use function base_path;
use function urlencode;
use function array_keys;
use function str_replace;

/**
 * @internal Single use trait for the experimental/internal self-update command.
 */
trait ReportsSelfUpdateCommandIssues
{
    protected function createIssueTemplateLink(Throwable $exception): string
    {
        return $this->buildUrl('https://github.com/hydephp/cli/issues/new', [
            'title' => 'Error while self-updating the application',
            'body' => $this->stripPersonalInformation($this->getIssueMarkdown($exception)),
        ]);
    }

    /** @param  array<string, string>  $params */
    protected function buildUrl(string $url, array $params): string
    {
        return sprintf("$url?%s", implode('&', array_map(function (string $key, string $value): string {
            return sprintf('%s=%s', $key, urlencode($value));
        }, array_keys($params), $params)));
    }

    protected function getDebugEnvironment(): string
    {
        return implode("\n", [
            'Application version: v'.$this->getAppVersion(),
            'PHP version:         v'.PHP_VERSION,
            'Operating system:    '.PHP_OS,
        ]);
    }

    protected function getIssueMarkdown(Throwable $exception): string
    {
        return <<<MARKDOWN
        ### Description
        
        A fatal error occurred while trying to update the application using the self-update command.
        
        ### Error message
        
        ```
        {$exception->getMessage()} on line {$exception->getLine()} in file {$exception->getFile()}
        ```
        
        ### Stack trace
        
        ```
        {$exception->getTraceAsString()}
        ```
        
        ### Environment
        
        ```
        {$this->getDebugEnvironment()}
        ```
        
        ### Context
        
        - Add any additional context here that may be relevant to the issue.
        
        MARKDOWN;
    }

    protected function stripPersonalInformation(string $markdown): string
    {
        // As the stacktrace may contain the user's name, we remove it to protect their privacy
        $markdown = str_replace(getenv('USER') ?: getenv('USERNAME'), '<USERNAME>', $markdown);

        // We also convert absolute paths to relative paths to avoid leaking the user's directory structure
        $markdown = str_replace(base_path().DIRECTORY_SEPARATOR, '<project>'.DIRECTORY_SEPARATOR, $markdown);

        return $markdown;
    }
}
