<?php

declare(strict_types=1);

namespace pointybeard\Symphony\Extensions\Console\Commands\Reflectionfield;
use pointybeard\Symphony\Extensions\Console;
use pointybeard\Symphony\Extensions\Console\Commands\Console\Symphony as SymphonyCommand;
use pointybeard\Helpers\Cli\Input;
use pointybeard\Helpers\Cli\Message;
use pointybeard\Helpers\Cli\Colour;
use pointybeard\Helpers\Cli\ProgressBar;
use pointybeard\Helpers\Foundation\BroadcastAndListen;

use Extension_ReflectionField;
use SectionManager;
use FieldManager;
use EntryManager;
use Exception;
use Symphony;

class Recompile extends Console\AbstractCommand implements Console\Interfaces\AuthenticatedCommandInterface, BroadcastAndListen\Interfaces\AcceptsListenersInterface
{
    use Console\Traits\hasCommandRequiresAuthenticateTrait;
    use BroadcastAndListen\Traits\HasListenerTrait;
    use BroadcastAndListen\Traits\HasBroadcasterTrait;

    public function __construct()
    {
        parent::__construct();
        $this
            ->description('Triggers the post entry save delegate, on all entries for a given section, which will cause the reflection field to recompile field vaules.')
            ->version('1.0.0')
            ->example('symphony reflectionfield recompile -t 1234 articles' . PHP_EOL . 'symphony reflectionfield recompile -t 1234 "articles,authors,comments"')
        ;
    }

    public function init(): void
    {
        parent::init();

        $this
            ->addInputToCollection(
                Input\InputTypeFactory::build('Argument')
                    ->name('sections')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL | Input\AbstractInputType::FLAG_VALUE_REQUIRED)
                    ->description('(optional) comma seperated list of section handles. Default: all sections')
                    ->validator(
                        function (Input\AbstractInputType $input, Input\AbstractInputHandler $context) {
                            $s = $context->find('sections');

                            $s = explode(",", $s);
                            $s = array_map("trim", $s);
                            $s = array_unique($s);

                            $sections = [];
                            foreach($s as $handle) {
                                if(null == $sectionId = SectionManager::fetchIDFromHandle($handle)) {
                                    throw new Exception("Section '{$handle}' does not exist!");
                                }
                                $sections[] = SectionManager::fetch((int)$sectionId);
                            }

                            return $sections;
                        }
                    )
            )
        ;
    }

    public function execute(Input\Interfaces\InputHandlerInterface $input): bool
    {

        // (guard) Not running on SymphonyCMS (Extended) <https://github.com/pointybeard/symphonycms>
        if(false == method_exists(Symphony::class, 'registerEngineInstance')) {
            $this->broadcast(
                SymphonyCommand::BROADCAST_MESSAGE,
                E_CRITICAL,
                (new Message\Message())
                    ->message("ERROR! This command requires SymphonyCMS (Extended). See more details at https://github.com/pointybeard/symphonycms")
                    ->foreground(Colour\Colour::FG_WHITE)
                    ->background(Colour\Colour::BG_RED),
                STDERR
            );
            exit(1);
        }

        $sections = $input->find('sections');

        // No sections specified, so grab them all
        if(null == $sections) {
            $sections = SectionManager::fetch();
        }

        $this->broadcast(
            SymphonyCommand::BROADCAST_MESSAGE,
            E_NOTICE,
            (new Message\Message())
                ->foreground(Colour\Colour::FG_GREEN)
                ->message(sprintf('Recompiling entries in %d section(s)...', count($sections)))
        );

        $showProgressBars = ((E_NOTICE & $input->find('v')) == E_NOTICE);

        foreach($sections as $section) {

            $this->broadcast(
                SymphonyCommand::BROADCAST_MESSAGE,
                E_NOTICE,
                (new Message\Message())
                    ->foreground(Colour\Colour::FG_GREEN)
                    ->message(sprintf('%s (%s): ', $section->get("name"), $section->get("handle")))
            );

            //(guard) Section does not contain any Reflection fields
            if(true == empty(FieldManager::fetch(null, $section->get("id"), 'ASC', 'sortorder', 'reflection'))) {
                $this->broadcast(
                    SymphonyCommand::BROADCAST_MESSAGE,
                    E_WARNING,
                    (new Message\Message())
                        ->message(sprintf("No Reflection fields located in '%s'. Skipping...", $section->get("handle")) . PHP_EOL)
                        ->foreground(Colour\Colour::FG_YELLOW)
                );
                continue;
            }

            $entries = EntryManager::fetch(null, $section->get("id"));

            // (guard) No entries to recompile
            if(true == empty($entries)) {
                $this->broadcast(
                    SymphonyCommand::BROADCAST_MESSAGE,
                    E_WARNING,
                    (new Message\Message())
                        ->message(sprintf("No entries located in '%s'. Skipping...", $section->get("handle")) . PHP_EOL)
                        ->foreground(Colour\Colour::FG_YELLOW)
                );
                continue;
            }

            $progress = (new ProgressBar\ProgressBar(count($entries)))
                ->length(30)
                ->foreground(Colour\Colour::FG_GREEN)
                ->background(Colour\Colour::BG_DEFAULT)
                ->format('{{PROGRESS_BAR}} {{COMPLETED}}/{{TOTAL}} ({{REMAINING_TIME}} remaining)')
            ;

            foreach($entries as $e) {

                // Simulate saving the entry via the interface
                Symphony::ExtensionManager()->notifyMembers('EntryPostEdit', '/publish/edit/', [
                    'section' => $section,
                    'entry' => $e
                ]);

                // Clear the registered fields so we get accurate results each time
                Extension_ReflectionField::deregisterFields();

                // Only show progress bars if verbosity is `-vvv` (E_NOTICE)
                if(true == $showProgressBars) {
                    $progress->advance();
                }

            }

            $this->broadcast(
                SymphonyCommand::BROADCAST_MESSAGE,
                E_NOTICE,
                (new Message\Message())
                    ->foreground(Colour\Colour::FG_GREEN)
                    ->message(PHP_EOL)
            );

        }

        $this->broadcast(
            SymphonyCommand::BROADCAST_MESSAGE,
            E_NOTICE,
            (new Message\Message())
                ->foreground(Colour\Colour::FG_GREEN)
                ->message(PHP_EOL)
        );

        return true;
    }
}
