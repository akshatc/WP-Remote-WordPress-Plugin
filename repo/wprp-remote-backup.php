<?php

use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;
use League\Flysystem\Sftp\SftpAdapter;

class WPRP_Remote_Backup {

    protected $config;

    /**
     * Upload the file
     *
     * @param $path
     * @param $contents
     * @return bool
     */
    public function upload($path, $contents)
    {
        try {
            $response = $this->getAdapter()->write($path, $contents);
        } catch (\Exception $exception) {
            return false;
        }

        return $response;
    }

    /**
     * Remove File
     *
     * @param $path
     * @return bool
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function remove( $path )
    {
        return $this->getAdapter()->delete($path);
    }

    /**
     * Set Config
     *
     * @param $config
     */
    public function setConfig( $config )
    {
        $this->config = $config;
    }

    /**
     * Get the current config
     *
     * @return array
     * @throws \Exception
     */
    protected function config() : array
    {
        // GET CONFIG
       /* $config = [
            'type' => 's3',
            'credentials' => [

            ]
        ];*/
       if (empty($this->config)) {
           throw new Exception('Config information not set');
       }
       return $this->config;
    }

    /**
     * Get Basic Config
     *
     * @param $type
     * @return array|bool
     */
    public function basicConfig( $type )
    {
        if ($type == 's3') {
            return [
                'credentials' => [
                    'key'    => 'your-key',
                    'secret' => 'your-secret',
                ],
                'region' => 'your-region',
                'version' => 'latest|version',
            ];
        } elseif ($type == 'dropbox') {
            return [
                'authorizationToken' => 'your-api-token'
            ];
        } elseif ($type == 'sftp') {
            return [
                'host' => 'example.com',
                'port' => 22,
                'username' => 'username',
                'password' => 'password',
                'privateKey' => 'path/to/or/contents/of/privatekey',
                'root' => '/path/to/root',
                'timeout' => 10,
            ];
        }
        return false;
    }

    /**
     * @return Filesystem
     */
    protected function getAdapter() : Filesystem
    {
        return $this->{$this->config()['type']};
    }

    /**
     * S3 Interface
     *
     * @return Filesystem
     */
    protected function s3() {
        /*
        $client = S3Client::factory([
            'credentials' => [
                'key'    => 'your-key',
                'secret' => 'your-secret',
            ],
            'region' => 'your-region',
            'version' => 'latest|version',
        ]);
        */

        $client = S3Client::factory($this->config()['credentials']);

        $adapter = new AwsS3Adapter($client, 'your-bucket-name', 'optional/path/prefix');

        $filesystem = new Filesystem($adapter);

        return $filesystem;
    }

    /**
     * DropBox Interface
     *
     * @return Filesystem
     */
    protected function dropbox()
    {
//        $client = new Client('$authorizationToken');
        $client = new Client($this->config()['credentials']['authorizationToken']);

        $adapter = new DropboxAdapter($client);

        $filesystem = new Filesystem($adapter);

        return $filesystem;
    }

    /**
     * SFTP Interface
     *
     * @return Filesystem
     */
    protected function sftp()
    {
        /*
        $filesystem = new Filesystem(new SftpAdapter([
            'host' => 'example.com',
            'port' => 22,
            'username' => 'username',
            'password' => 'password',
            'privateKey' => 'path/to/or/contents/of/privatekey',
            'root' => '/path/to/root',
            'timeout' => 10,
        ]));*/

        $filesystem = new Filesystem(
            new SftpAdapter( $this->config()['credentials'] )
        );

        return $filesystem;
    }
}