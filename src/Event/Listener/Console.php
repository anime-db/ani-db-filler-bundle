<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\AniDbFillerBundle\Event\Listener;

use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Update the titles db on clear cache
 *
 * @package AnimeDb\Bundle\AniDbFillerBundle\Event\Listener
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Console
{
    /**
     * Root dir
     *
     * @var string
     */
    protected $root_dir;

    /**
     * Construct
     *
     * @param string $root_dir
     */
    public function __construct($root_dir)
    {
        $this->root_dir = $root_dir;
    }

    /**
     * On Terminate command
     *
     * @param \Symfony\Component\Console\Event\ConsoleTerminateEvent $event
     */
    public function onTerminate(ConsoleTerminateEvent $event)
    {
        if ($event->getCommand()->getName() == 'cache:clear') {
            $env = ltrim($event->getInput()->getOption('env'), '=');
            $cmd = 'animedb:update-titles -e='.$env;

            $phpFinder = new PhpExecutableFinder();
            if (!($phpPath = $phpFinder->find())) {
                throw new \RuntimeException('The php executable could not be found, add it to your PATH environment variable and try again');
            }

            $php = escapeshellarg($phpPath);
            $process = new Process($php.' app/console '.$cmd, $this->root_dir.'/../', null, null, 1500);
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });
            if (!$process->isSuccessful()) {
                throw new \RuntimeException(sprintf('An error occurred when executing the "%s" command.', $cmd));
            }
        }
    }
}