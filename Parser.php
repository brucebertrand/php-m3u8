<?php

namespace Chrisyue\PhpM3u8;

use Chrisyue\PhpM3u8\Loader\LoaderInterface;
use Chrisyue\PhpM3u8\M3u8\M3u8;
use Chrisyue\PhpM3u8\M3u8\MediaSegment\MediaSegment;
use Chrisyue\PhpM3u8\M3u8\Playlist;

class Parser
{
    private $loader;

    public function setLoader(LoaderInterface $loader)
    {
        $this->loader = $loader;

        return $this;
    }

    public function parseFromUri($uri)
    {
        if (null === $this->loader) {
            throw new \Exception('You should set an m3u8 loader first');
        }

        return $this->parse($this->loader->load($uri));
    }

    public function parse($content)
    {
        $data = $this->content2Data($content);

        $version = 3;
        $mediaSequence = 0;

        extract($data); // to $version, $mediaSequence, $targetDuration

        $playlist = new Playlist();
        foreach ($data['playlist'] as $index => $row) {
            $mediaSegment = new MediaSegment(
                $row['uri'],
                $row['duration'],
                $mediaSequence + $index,
                !empty($row['isDiscontinuity'])
            );
            $playlist->add($mediaSegment);
        }

        return new M3u8($playlist, $version, $targetDuration);
    }

    private function content2Data($content)
    {
        $data = [];

        $mediaSequence = 0;

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (preg_match('/^#EXT-X-VERSION:(\d+)/', $line, $matches)) {
                $data['version'] = $matches[1];
                continue;
            }

            if (preg_match('/^#EXT-X-TARGETDURATION:(\d+)/', $line, $matches)) {
                $data['targetDuration'] = $matches[1];
                continue;
            }

            if (preg_match('/^#EXT-X-MEDIA-SEQUENCE:(\d+)/', $line, $matches)) {
                $data['mediaSequence'] = $matches[1];
                continue;
            }

            if (preg_match('/^#EXT-X-DISCONTINUITY/', $line)) {
                $data['playlist'][$mediaSequence]['isDiscontinuity'] = true;
            }

            if (preg_match('/^#EXTINF:(.+),/', $line, $matches)) {
                $data['playlist'][$mediaSequence]['duration'] = $matches[1];
                continue;
            }

            if (preg_match('/^[^#]+/', $line, $matches)) {
                $data['playlist'][$mediaSequence]['uri'] = $matches[0];
                ++$mediaSequence;
            }
        }

        return $data;
    }
}