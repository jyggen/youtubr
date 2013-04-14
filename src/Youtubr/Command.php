<?php
namespace Youtubr;

use jyggen\Curl;

class Command extends \Symfony\Component\Console\Command\Command
{

	protected function executeExternal($cmd)
	{

		$stdout         = array();
		$stderr         = array();
		$outfile        = tempnam(sys_get_temp_dir(), 'youtubr');
		$errfile        = tempnam(sys_get_temp_dir(), 'youtubr');
		$descriptorspec = array(0 => array('pipe', 'r'), 1 => array('file', $outfile, 'w'), 2 => array('file', $errfile, 'w'));
		$proc           = proc_open(escapeshellcmd($cmd), $descriptorspec, $pipes);

		if (!is_resource($proc)) {
			throw new \Exception('Unable to execute command.');
		}

		fclose($pipes[0]); //Don't really want to give any input

		$exit   = proc_close($proc);
		$stdout = file($outfile);
		$stderr = file($errfile);

		unlink($outfile);
		unlink($errfile);

		return array($stdout, $stderr);

	}

	protected function isInstalled($cmd)
	{

		$command = (defined('PHP_WINDOWS_VERSION_BUILD')) ? 'where %s 2> NUL' : 'command -v %s 2> /dev/null';
		$output  = $this->executeExternal(sprintf($command, escapeshellarg($cmd)));

		return (empty($output[0])) ? false : true;

	}

	protected function getBestAvailableStream($id)
	{

		$return  = $this->executeExternal('cclive -S '.escapeshellarg('http://www.youtube.com/watch?v='.$id));
		$return  = $return[1];
		$formats = array();

		unset($return[0]);

		foreach ($return as $format) {
			$format = trim($format);
			preg_match('/^fmt([\d]{1,3})_/', $format, $itag);
			$formats[intval($itag[1])] = $format;

		}

		if (isset($formats[38])) {
			return $formats[38];
		} elseif (isset($formats[37])) {
			return $formats[37];
		} elseif (isset($formats[22])) {
			return $formats[22];
		} elseif (isset($formats[18])) {
			return $formats[18];
		} else {
			throw new \Exception('No H.264 stream found.');
		}

	}

	protected function retrievePlaylist($id, $index = 1, $limit = 25)
	{

		$response = Curl::get(sprintf('https://gdata.youtube.com/feeds/api/playlists/%s?v=2&alt=json&start-index=%u&max-results=%u', $id, $index, $limit));

		if ($response->getStatusCode() !== 200) {

			$output->writeln('<error>That playlist is either private or does not exist.</error>');
			return 1;

		}

		$data = json_decode($response->getContent(), true);

		if ($data === null) {

			$output->writeln('<error>Could not parse JSON response from Youtube API.</error>');
			return 1;

		}

		$nextPage = false;
		foreach ($data['feed']['link'] as $link) {
			if ($link['rel'] === 'next') {
				$nextPage = true;
				break;
			}
		}

		if ($nextPage === true) {
			$data2 = $this->retrievePlaylist($id, $index+$limit, $limit);
			$data['feed']['entry'] = array_merge($data['feed']['entry'], $data2['feed']['entry']);
		}

		return $data;

	}

	protected function sanitizeFilename($str)
	{

		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
		$clean = preg_replace('/[^a-zA-Z0-9\/_|+ -]/','', $clean);
		$clean = preg_replace('/[\/_|+ -]+/', '-', $clean);
		$clean = trim($clean, '-');

		return $clean;

	}

}