<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class FeedCommand
 * @package AppBundle\Command
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
class FeedCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:feed')
            ->addOption('all', 'a',InputOption::VALUE_NONE, 'the feed mode')
            ->addArgument('merchantCode', InputArgument::OPTIONAL, 'The merchant code')
            ->setDescription('Reads the given merchant\'s data feed and create or updates offers.')
            ->setHelp(<<<EOT
La commande app:feed permet de récupérer les offres des sites marchands affiliés d'après leur flux de données :

    Ex: php app/console app:feed 123456 
EOT
);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // récupérer l'option
        $getAll = $input->getOption('all');

        // Récupérer le service "Feed Reader"
        $reader = $this->getContainer()->get('app.feed.reader');

        // Récupérer le service "Doctrine Entity Manager"
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');

        // repo
        $code = $input->getArgument('merchantCode');
        $repo = $em->getRepository('AppBundle:Merchant');

        // Trouver unmarchand d'après le code passé en argument de la commande
        if( !$getAll) {

            $code = $input->getArgument('merchantCode');

            if( $code == null) {
                $output->writeln('il faut préciser un code marchant');
                return;
            }

            $merchants = $repo->findByCode($code);
        }
        // trouver tous les marchants
        else  {
            $merchants = $repo->findAll();
        }

        // Utiliser le service "Feed Reader" pour récupérer les offres du flux du marchand
        $result = 0;
        foreach($merchants as $merchant) {
            $n = $merchant->getName();
            $output->writeln("Fetching merchant $n");
            $result += $reader->read($merchant);
        }

        // mail
        $message = \Swift_Message::newInstance()
            ->setTo('gabriel.daudin@nantes.imie.fr')
            ->setFrom('gabriel.daudin@nantes.imie.fr')
            ->setSubject('Update done')

            /*
             * From a controller, next expression should be :
             *
             *  ->setBody($this->renderView(
                'AppBundle:Email:contact.html.twig', [
                    'result' =>  $result
                ]
            ), 'text/html');
             */

            ->setBody($this->getContainer()->get('templating')->render(
                'AppBundle:Email:contact.html.twig', [
                    'result' =>  $result
                ]
            ), 'text/html');
        $sent = $this->getContainer()->get('mailer')->send($message);

        // Afficher (dans le terminal) le nombre d'offres créées ou mises à jour.
        $output->writeln("$result offres mise(s) à jour");
    }
}
