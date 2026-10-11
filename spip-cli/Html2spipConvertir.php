<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Html2spipConvertir extends Command {
	protected function configure(): void {
		$this
			->setName('html2spip:convertir')
			->setDescription('Convertit du HTML, lu sur l’entrée standard, en raccourcis SPIP')
			->setHelp('Le HTML peut venir de SPIP (propre()) : liens internes, notes, modèles doc/img/emb et typographie sont retrouvés. Avec -v, les avertissements (liens non résolus, notes incomplètes) sont écrits sur la sortie d’erreur.')
			->addOption('langue', 'l', InputOption::VALUE_REQUIRED, 'Langue du texte, qui choisit la typographie à inverser (langue du site par défaut)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		global $spip_racine;
		global $spip_loaded;

		if (!$spip_loaded) {
			$output->writeln('<error>Vous devez lancer la commande depuis un site SPIP.</error>');
			return Command::FAILURE;
		}
		chdir($spip_racine);
		include_spip('inc/html2spip');
		$output->writeln(html2spip((string) stream_get_contents(STDIN), $input->getOption('langue') ?: null), OutputInterface::OUTPUT_RAW);
		if ($output->isVerbose()) {
			$erreur = ($output instanceof ConsoleOutputInterface) ? $output->getErrorOutput() : $output;
			foreach (html2spip_avertissements() as $avertissement) {
				$erreur->writeln($avertissement, OutputInterface::OUTPUT_RAW);
			}
		}
		return Command::SUCCESS;
	}
}
