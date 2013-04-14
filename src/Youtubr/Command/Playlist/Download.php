<?php
namespace Youtubr\Command\Playlist;

use jyggen\Curl;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Youtubr\Command;

class Download extends Command
{

	protected function configure()
	{

		$this->setName('playlist:download')
		     ->setDescription('Download a playlist')
		     ->addArgument('id', InputArgument::REQUIRED, 'The ID of the playlist to download.')
		     ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Where the downloaded videos should be saved.', './');

	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{

		if (!$this->isInstalled('cclive')) {

			$output->writeln('<error>cclive is required for this command to work.</error>');
			return 1;

		}

		$id   = $input->getArgument('id');
		$path = realpath($input->getOption('path'));

		if ($path === false) {

			$output->writeln(sprintf('<error>The specified path does not exist ("%s").</error>', $input->getOption('path')));
			return 1;

		}

		$data = Curl::get(sprintf('https://gdata.youtube.com/feeds/api/playlists/%s?v=2&alt=json', $id));

		if ($data->getStatusCode() !== 200) {

			$output->writeln('<error>That playlist is either private or does not exist.</error>');
			return 1;

		}

		$data = json_decode($data->getContent(), true);

		if ($data === null) {

			$output->writeln('<error>Could not parse JSON response from Youtube API.</error>');
			return 1;

		}

		$videos   = $data['feed']['entry'];
		$files    = array();
		$progress = $this->getHelperSet()->get('progress');

		$output->writeln('<info>Downloading videos:</info>');
		$progress->start($output, count($videos));

		foreach ($videos as $video) {

			$id     = $video['media$group']['yt$videoid']['$t'];
			$title  = $video['title']['$t'];
			$format = escapeshellarg($this->getBestAvailableStream($id));

			$this->executeExternal('cclive -q --output-dir '.escapeshellarg($path).' -s '.$format.' '.escapeshellarg('http://www.youtube.com/watch?v='.$id));
			$progress->advance();

		}

		$progress->finish();

		$output->writeln('');
		$output->writeln(sprintf('<info>Files successfully saved to "%s".</info>', $path));
		return 0;

	}

}

//youtubr download:playlist 123123
//youtubr download:video 12312312
//youtubr export:playlist 123123
//youtubr export:video 123123