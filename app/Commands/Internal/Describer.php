<?php

declare(strict_types=1);

namespace App\Commands\Internal;

use App\Launcher\Launcher;
use Illuminate\Console\Application;
use Illuminate\Contracts\Config\Repository;
use Symfony\Component\Console\Output\OutputInterface;
use NunoMaduro\LaravelConsoleSummary\Describer as BaseDescriber;
use NunoMaduro\LaravelConsoleSummary\Contracts\DescriberContract;

use function max;
use function ksort;
use function sprintf;
use function explode;
use function implode;
use function defined;
use function in_array;
use function mb_strlen;
use function str_repeat;

/**
 * @internal Renders the command list, in sections that say where each command runs.
 *
 * The `hyde` executable answers some commands itself, wherever it is run, and hands
 * everything else to the project it was invoked against. That distinction decides
 * what a command can do and what it acts on, so the list states it rather than
 * leaving one flat alphabetical run of names to imply they are all alike.
 *
 * Membership of the CLI section is read from the launcher's own constants, so the
 * list cannot come to disagree with the routing it is describing.
 *
 * @see \App\Launcher\Launcher::ownedCommands()
 */
class Describer extends BaseDescriber
{
    /** The heading for the commands the executable answers itself, and what it means. */
    protected const CLI_SECTION = ['HydeCLI', 'Works from any directory'];

    /** The heading for everything the project owns, and what it means. */
    protected const PROJECT_SECTION = ['Hyde project', 'Commands available in the current Hyde project'];

    /** @var array<string, string> The compact, user-facing project command groups. */
    protected const PROJECT_GROUPS = [
        '' => 'Core',
        'build' => 'Build',
        'make' => 'Create',
        'publish' => 'Publish',
        '*' => 'Other',
    ];

    public function __construct(private readonly Repository $config)
    {
        parent::__construct($config);
    }

    /**
     * Describe the usage line.
     *
     * The package's own configuration defaults the binary name to null rather than
     * leaving it unset, so its fallback to `ARTISAN_BINARY` never fires and the line
     * reads `USAGE:  <command>`. The name of the program is the useful half.
     */
    protected function describeUsage(OutputInterface $output): DescriberContract
    {
        $binary = $this->config->get('laravel-console-summary.binary')
            ?: (defined('ARTISAN_BINARY') ? ARTISAN_BINARY : 'hyde');

        $output->write("  <fg=yellow;options=bold>USAGE:</> {$binary} <command> [options] [arguments]\n");

        return $this;
    }

    protected function describeCommands(Application $application, OutputInterface $output): DescriberContract
    {
        $commands = $this->visibleCommands($application);

        $cli = [];

        foreach (Launcher::ownedCommands() as $name) {
            if (isset($commands[$name])) {
                $cli[] = $commands[$name];

                unset($commands[$name]);
            }
        }

        if ($cli !== []) {
            $this->describeSection($output, self::CLI_SECTION, ['' => $cli], 4);
        }

        $this->describeSection($output, self::PROJECT_SECTION, $this->groupProjectCommands($commands), 6, 4);

        $output->writeln('');

        return $this;
    }

    /**
     * The commands that belong in the list at all, keyed by name.
     *
     * Hidden commands, and anything the `laravel-console-summary.hide` configuration
     * names, are left out. Wildcards there match a whole namespace.
     *
     * @return array<string, \Symfony\Component\Console\Command\Command>
     */
    protected function visibleCommands(Application $application): array
    {
        $hidden = (array) $this->config->get('laravel-console-summary.hide', []);

        $commands = [];

        foreach ($application->all() as $command) {
            $name = (string) $command->getName();

            if ($command->isHidden() || in_array($name, $hidden, true) || in_array(explode(':', $name)[0].':*', $hidden, true)) {
                continue;
            }

            $commands[$name] = $command;
        }

        ksort($commands);

        return $commands;
    }

    /**
     * Split the project's commands into compact, user-facing groups.
     *
     * @param  array<string, \Symfony\Component\Console\Command\Command>  $commands
     * @return array<string, list<\Symfony\Component\Console\Command\Command>>
     */
    protected function groupProjectCommands(array $commands): array
    {
        $groups = [];

        foreach ($commands as $name => $command) {
            $parts = explode(':', $name);
            $namespace = isset($parts[1]) ? $parts[0] : '';
            $group = self::PROJECT_GROUPS[$namespace] ?? self::PROJECT_GROUPS['*'];

            $groups[$group][] = $command;
        }

        $ordered = [];

        foreach (['Core', 'Build', 'Create', 'Publish', 'Other'] as $group) {
            if (isset($groups[$group])) {
                $ordered[$group] = $groups[$group];
            }
        }

        return $ordered;
    }

    /**
     * @param  array{0: string, 1: string}  $section
     * @param  array<string, list<\Symfony\Component\Console\Command\Command>>  $groups
     */
    protected function describeSection(
        OutputInterface $output,
        array $section,
        array $groups,
        int $commandIndent,
        ?int $groupIndent = null,
    ): void
    {
        $groupIndent ??= $commandIndent;

        $output->write(sprintf("\n  <fg=yellow;options=bold>%s</>\n    <fg=gray>%s</>\n", $section[0], $section[1]));

        foreach ($groups as $index => $commands) {
            $width = 0;

            foreach ($commands as $command) {
                $width = max($width, mb_strlen((string) $command->getName()));
            }

            $output->write("\n");

            if ($index !== '') {
                $output->write(sprintf("%s<fg=yellow>%s</>\n", str_repeat(' ', $groupIndent), $index));
            }

            foreach ($commands as $command) {
                $output->write(sprintf(
                    "%s<fg=green>%s</>%s%s%s\n",
                    str_repeat(' ', $commandIndent),
                    $command->getName(),
                    str_repeat(' ', $width - mb_strlen((string) $command->getName()) + 1),
                    $command->getAliases() ? '<fg=cyan>[</>'.implode('|', $command->getAliases()).'<fg=cyan>]</> ' : '',
                    $command->getDescription()
                ));
            }
        }
    }
}
