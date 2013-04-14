<?php
namespace Youtubr\Command\Playlist;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Youtubr\Command;

class Merge extends Command
{

	protected function configure()
	{

		$this->setName('playlist:merge')
		     ->setDescription('Merge a playlist into a single matroska (.mkv) file.')
		     ->addArgument('id', InputArgument::REQUIRED, 'The ID of the playlist to download.')
		     ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Where the output file should be saved.', './')
		     ->addOption('select', 's', InputOption::VALUE_NONE, 'Select interactively which videos in the playlist you want to merge.')
		;

	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{

		if (!$this->isInstalled('cclive')) {

			$output->writeln('<error>cclive is required for this command to work.</error>');
			return 1;

		}

		if (!$this->isInstalled('mkvmerge')) {

			$output->writeln('<error>mkvmerge is required for this command to work.</error>');
			return 1;

		}

		$id   = $input->getArgument('id');
		$path = realpath($input->getOption('path'));

		if ($path === false) {

			$output->writeln(sprintf('<error>The specified path does not exist ("%s").</error>', $input->getOption('path')));
			return 1;

		}

		$data       = $this->retrievePlaylist($id);
		$videos     = $data['feed']['entry'];
		$outputName = $this->sanitizeFilename($data['feed']['title']['$t']);
		$progress   = $this->getHelperSet()->get('progress');
		$select     = $input->getOption('select');

		if ($select === true) {
			$dialog = $this->getHelperSet()->get('dialog');
			foreach ($videos as $key => $video) {
				if (!$dialog->askConfirmation($output, '<question>Would you like to include "'.$video['title']['$t'].'"?</question>')) {
					unset($videos[$key]);
				}
			}
		}

		$output->writeln('<info>Downloading and encoding videos:</info>');
		$progress->start($output, count($videos)*2);

		foreach ($videos as $video) {

			$id    = $video['media$group']['yt$videoid']['$t'];
			$title = $video['title']['$t'];
			$file  = tempnam(sys_get_temp_dir(), 'youtubr');
			$file2 = tempnam(sys_get_temp_dir(), 'youtubr');

			rename($file2, $file2.'.mp4');

			$tmpFiles[] = $file2.'.mp4';
			$format     = escapeshellarg($this->getBestAvailableStream($id));

			$this->executeExternal('cclive -q --output-file '.escapeshellarg($file).' -s '.$format.' '.escapeshellarg('http://www.youtube.com/watch?v='.$id));
			$progress->advance();
			$this->executeExternal('avconv -i '.escapeshellarg($file).' -y -vcodec libx264 -preset medium -s hd720 -crf 21 -acodec copy '.escapeshellarg($file2.'.mp4'));
			$progress->advance();

			unlink($file);

		}

		$progress->finish();
		$output->write('<info>Merging into a matroska container ... </info>');

		$mkvFiles = array();
		foreach ($tmpFiles as $file) {
			$mkvFiles[] = escapeshellarg($file);
		}

		$mkvFiles   = implode(' + ', $mkvFiles);
		$outputFile = escapeshellarg($path.'/'.$outputName.'.mkv');

		$this->executeExternal('mkvmerge -o '.$outputFile.' '.$mkvFiles);
		$output->writeln('<info>OK!</info>');

		foreach ($tmpFiles as $file) {
			unlink($file);
		}

		$output->writeln(sprintf('<info>Files successfully saved to %s.</info>', $outputFile));

	}

}