<?php
/**
 * Created by PhpStorm.
 * User: Philoupe
 * Date: 29/07/2017
 * Time: 21:16
 */

namespace AppBundle\Services;


use AppBundle\Entity\Album;
use AppBundle\Entity\Artist;
use AppBundle\Entity\Genre;
use AppBundle\Entity\PlayedSong;
use AppBundle\Entity\Song;
use AppBundle\Entity\SongStats;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\SpotifyResourceOwner;

class UpdateUserHistoryService
{

    private $em;
    private $spotify;

    /**
     * UpdateUserHistoryService constructor.
     * @param $em
     */
    public function __construct(EntityManager $em, SpotifyResourceOwner $spotify)
    {
        $this->em = $em;
        $this->spotify = $spotify;
    }

    function refreshToken(User $user, $flush = false) {
        $result = $this->spotify->refreshAccessToken($user->getRefreshToken());
        if(isset($result["access_token"]))
            $user->setToken($result["access_token"]);
        $this->em->persist($user);
        if($flush) {
            $this->updateSongAlbumAndStats($user);
            $this->updateAlbumsGenres($user);
            $this->em->flush();
        }
    }

    function updateUsersHistory()
    {
        $users = $this->em->getRepository('AppBundle:User')->findAll();
        foreach ($users as $user) {
            $this->refreshToken($user);
            $this->updateUserHistory($user);
        }
        $this->updateSongAlbumAndStats($users[0]);
        $this->updateAlbumsGenres($users[0]);
        $this->em->flush();
    }

    function updateUserHistory(User $user, $flush = false) {
            $lastFetch = $user->getLastFetch();
            if($lastFetch)
                $ch = curl_init("https://api.spotify.com/v1/me/player/recently-played?limit=50&after=".$lastFetch);
            else
                $ch = curl_init("https://api.spotify.com/v1/me/player/recently-played?limit=49");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $user->getToken()
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);
            curl_close($ch);
            $api_response = json_decode($server_output, true);
            $artists = $songs = $songsPlayed = [];
            if(isset($api_response["items"])) {
                foreach ($api_response["items"] as $recently_played_item) {
                    $artist = null;
                    if(isset($artists[$recently_played_item["track"]["artists"][0]["id"]]))
                        $artist = $artists[$recently_played_item["track"]["artists"][0]["id"]];
                    if (!is_object($artist))
                        $artist = $this->em->getRepository("AppBundle:Artist")->findOneBy(array("artistId" => $recently_played_item["track"]["artists"][0]["id"]));
                    if (!is_object($artist)) {
                        $artist = new Artist($recently_played_item["track"]["artists"][0]["id"], $recently_played_item["track"]["artists"][0]["name"]);
                        $this->em->persist($artist);
                    }
                    $artists[$recently_played_item["track"]["artists"][0]["id"]] = $artist;
                    $song = null;
                    if(isset($songs[$recently_played_item["track"]["id"]]))
                        $song = $songs[$recently_played_item["track"]["id"]];
                    if (!is_object($song))
                        $song = $this->em->getRepository("AppBundle:Song")->findOneBy(array("songId" => $recently_played_item["track"]["id"]));
                    if (!is_object($song)) {
                        $song = new Song($recently_played_item["track"]["id"], $recently_played_item["track"]["name"], $artist);
                        $this->em->persist($song);
                    }
                    $songs[$recently_played_item["track"]["id"]] = $song;
                    $songPlayed = null;
                    if(isset($songsPlayed[$recently_played_item["track"]["id"]]))
                        $songPlayed = $songsPlayed[$recently_played_item["track"]["id"]];
                    if (!is_object($songPlayed))
                        $songPlayed = $this->em->getRepository("AppBundle:PlayedSong")->findOneBy(array("song" => $song, "user" => $user));
                    if (!is_object($songPlayed)) {
                        $songPlayed = new PlayedSong($song, $user);
                    } else {
                        $songPlayed->addCount();
                    }
                    $songsPlayed[$recently_played_item["track"]["id"]] = $songPlayed;
                    $this->em->persist($songPlayed);
                }
                if(isset($api_response["cursors"]["after"]))
                    $user->setLastFetch($api_response["cursors"]["after"]);
                $this->em->persist($user);
                if ($flush)
                    $this->em->flush();
            }
    }

    private function updateAlbumsGenres(User &$user)
    {
        $albums = $this->em->getRepository('AppBundle:Album')->findAll();
        $albumsByIds = [];
        foreach ($albums as &$album) {
            $albumsByIds[$album->getAlbumId()] = &$album;
        }
        $genres = $this->em->getRepository('AppBundle:Genre')->findAll();
        $genresByNames = [];
        foreach ($genres as &$genre) {
            $genresByNames[$genre->getName()] = &$genre;
        }

        $albumsIds = array_chunk(array_keys($albumsByIds), 20);
        foreach ($albumsIds as $albumsIdsSubArray) {
            $ch = curl_init("https://api.spotify.com/v1/albums?ids=" . implode(",", $albumsIdsSubArray));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $user->getToken()
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);
            curl_close($ch);
            $albumsInfos = json_decode($server_output, true)["albums"];
            foreach ($albumsInfos as $albumInfos) {
                $album = $albumsByIds[$albumInfos['id']];
                foreach ($albumInfos['genres'] as $genreName) {
                    if (isset($genres[$genreName]))
                        $genre = $genres[$genreName];
                    else {
                        $genre = new Genre();
                        $genre->setName($genreName);
                        $this->em->persist($genre);
                        $genres[$genreName] = $genre;
                    }
                    $album->addGenre($genre);
                }
                $this->em->persist($album);
            }
        }
    }

    private function updateSongAlbumAndStats(User &$user)
    {
        $songs = $this->em->getRepository('AppBundle:Song')->findAll();
        $songsByIds = [];
        foreach ($songs as $songEntity) {
            $songsByIds[$songEntity->getSongId()] = $songEntity;
        }
        $albums = $this->em->getRepository('AppBundle:Album')->findAll();
        $albumsByIds = [];
        foreach ($albums as &$album) {
            $albumsByIds[$album->getAlbumId()] = &$album;
        }
        $songsIds = array_chunk(array_keys($songsByIds), 50);
        foreach ($songsIds as $songsIdsSubArray) {
            $ch = curl_init("https://api.spotify.com/v1/tracks?ids=" . implode(",", $songsIdsSubArray));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $user->getToken()
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);
            curl_close($ch);
            $songInfos = json_decode($server_output, true)["tracks"];
            $ch = curl_init("https://api.spotify.com/v1/audio-features?ids=" . implode(",", $songsIdsSubArray));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $user->getToken()
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);
            curl_close($ch);
            $songFeatures = json_decode($server_output, true)["audio_features"];
            foreach ($songInfos as $key => $song)
                if(!empty($song))
                {
                    $songEntity = $songsByIds[$song["id"]];
                    if(!isset($albumsByIds[$song["album"]['id']])) {
                        $albumsByIds[$song["album"]['id']] = new Album(
                            $song["album"]['id'],
                            $song["album"]['name'],
                            $song["album"]['images'][0]['url']);
                        $this->em->persist($albumsByIds[$song["album"]['id']]);
                    }
                    $songEntity->setAlbum($albumsByIds[$song["album"]['id']]);
                    if(!is_object($songStats = $songEntity->getStats())) {
                        $songStats = new SongStats();
                        $this->em->persist($songStats);
                        $songEntity->setStats($songStats);
                    }
                    $songStats->setPopularity($song['popularity'])
                        ->setAcousticness($songFeatures[$key]['acousticness'])
                        ->setDanceability($songFeatures[$key]['danceability'])
                        ->setEnergy($songFeatures[$key]['energy'])
                        ->setInstrumentalness($songFeatures[$key]['instrumentalness'])
                        ->setSongKey($songFeatures[$key]['key'])
                        ->setLiveness($songFeatures[$key]['liveness'])
                        ->setLoudness($songFeatures[$key]['loudness'])
                        ->setSpeechiness($songFeatures[$key]['speechiness'])
                        ->setTempo($songFeatures[$key]['tempo'])
                        ->setValence($songFeatures[$key]['valence'])
                    ;
                    $this->em->persist($songStats);
                    $this->em->persist($songEntity);
                }
        }
    }



}