<?php
namespace Youtubr\Command\Video;

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

        $this->setName('video:download')
             ->setDescription('Download a video')
             ->addArgument('id', InputArgument::REQUIRED, 'The ID of the video to download.')
             ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Where the downloaded video should be saved.', './')
             ->addOption('subtitle', 's', InputOption::VALUE_OPTIONAL, 'A subtitle to attach to the .mkv file.', null);

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        if (!$this->isInstalled('cclive')) {

            $error = '<error>cclive is required for this command to work.</error>';
            $output->writeln($error);
            return 1;

        }

        $id   = $input->getArgument('id');
        $path = realpath($input->getOption('path'));

        if ($path === false) {

            $error = '<error>The specified path does not exist ("'.$input->getOption('path').'").</error>';
            $output->writeln($error);
            return 1;

        }

        $subtitle = realpath($input->getOption('subtitle'));

        if ($input->getOption('subtitle') !== null and $subtitle === false) {

            $error = '<error>The specified subtitle does not exist ("'.$input->getOption('subtitle').'").</error>';
            $output->writeln($error);
            return 1;

        }

        $data = Curl::get(sprintf('https://gdata.youtube.com/feeds/api/videos/%s?v=2&alt=json', $id));

        if ($data->getStatusCode() !== 200) {

            $error = '<error>That playlist is either private or does not exist.</error>';
            $output->writeln($error);
            return 1;

        }

        $data = json_decode($data->getContent(), true);

        if ($data === null) {

            $error = '<error>Could not parse JSON response from Youtube API.</error>';
            $output->writeln($error);
            return 1;

        }

        $video  = $data['entry'];
        $id     = $video['media$group']['yt$videoid']['$t'];
        $title  = $video['title']['$t'];
        $file   = tempnam(sys_get_temp_dir(), 'youtubr');
        $file2  = tempnam(sys_get_temp_dir(), 'youtubr');
        $format = escapeshellarg($this->getBestAvailableStream($id));

        rename($file2, $file2.'.mp4');

        $command    = 'cclive -q --output-file %s -s %s %s';
        $outputFile = escapeshellarg($file);
        $inputFile  = escapeshellarg('http://www.youtube.com/watch?v='.$id);

        $this->executeExternal(sprintf($command, $outputFile, $format, $inputFile));

        $command    = 'avconv -i %s -y -vcodec libx264 -preset medium -s hd720 -crf 21 -acodec copy %s';
        $outputFile = escapeshellarg($file2.'.mp4');
        $inputFile  = escapeshellarg($file);

        $this->executeExternal(sprintf($command, $inputFile, $outputFile));

        unlink($file);

        $outputFile = escapeshellarg($path.$title.'.mkv');

        if ($subtitle !== false) {
            $this->executeExternal('mkvmerge -o '.$outputFile.' '.escapeshellarg($file2.'.mp4').' '.escapeshellarg($subtitle));
        } else {
            $this->executeExternal('mkvmerge -o '.$outputFile.' '.escapeshellarg($file2.'.mp4'));
        }

        unlink($file2.'mp4');

        return 0;

    }
}
