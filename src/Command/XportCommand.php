<?php

declare(strict_types=1);

namespace Aquis\XporterBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Class XportCommand.
 *
 * @copyright Aquis Grana impex srl (http://www.webnou.ro/)
 * @author    Petronel Malutan <malutanpetronel@gmail.com>
 */
class XportCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('aquis:xporter:info')
            ->setDescription('About the XPORTER plugin')
            ->setHelp(<<<EOT
Aquis Xporter plugin allow you tp creates a DB YML dump starting from one <info>entity</info> specified by id, 
or a comma separated id list, and all related entities recursively identified by the mapping description.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->write('<comment>     Aquis Xporter plugin allow you tp creates a DB YML dump starting from one <info>entity</info> specified by id, 
or a comma separated id list, and all related entities recursively identified by the mapping description.

    <question>bin/console  aquis:fixture:dump App\\Entity\\Product\\Product --ids=1,3 --debug=true</question>
    
    Please note that this plugin always export the users, so the result is always containing something even if your specified entity and id does not exist.
    
    Enjoy!
</comment>
'.PHP_EOL);

        return 0;
    }
}
