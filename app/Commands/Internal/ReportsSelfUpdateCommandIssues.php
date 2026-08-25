<?php

namespace App\Commands\Internal;

use Throwable;

use function rtrim;
use function getenv;
use function implode;
use function sprintf;
use function array_map;
use function base_path;
use function urlencode;
use function preg_quote;
use function preg_split;
use function array_keys;
use function str_replace;
use function preg_replace_callback;

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

        // We also convert absolute paths to relative paths to avoid leaking the user's directory
        // structure. The root reaches this text in more than one spelling: the launcher
        // holds it canonical (`C:/Users/emma/site`), a Windows stack trace writes it
        // native (`C:\Users\emma\site`), and anything that joined it with
        // DIRECTORY_SEPARATOR is a mixture of the two. Matching one spelling
        // would publish the others in a public issue URL, so the separator
        // is matched as a class rather than as a character.
        $root = rtrim(base_path(), '/\\');

        $pattern = '#'.implode('[/\\\\]+', array_map(
            static fn (string $segment): string => preg_quote($segment, '#'),
            preg_split('#[/\\\\]#', $root)
        )).'[/\\\\]#';

        // A callback, because DIRECTORY_SEPARATOR is a backslash on Windows and a
        // replacement string ending in one is read as an escape by PCRE.
        return preg_replace_callback($pattern, static fn (): string => '<project>'.DIRECTORY_SEPARATOR, $markdown);
    }
}
