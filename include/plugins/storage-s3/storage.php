<?php

use Aws\S3\Exception\SignatureDoesNotMatchException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Stream;
require_once INCLUDE_DIR . 'class.json.php';

// Register autoloader for plugin lib directory
if (file_exists(__DIR__ . '/lib')) {
    require_once INCLUDE_DIR . 'UniversalClassLoader.php';
    if (class_exists('Symfony\Component\ClassLoader\UniversalClassLoader_osTicket')) {
        $loader = new Symfony\Component\ClassLoader\UniversalClassLoader_osTicket();
        $loader->registerNamespaceFallbacks(array(__DIR__ . '/lib'));
        $loader->register();
    }
}

// Load helper functions using explicit paths
if (file_exists(__DIR__ . '/lib/Aws/functions.php')) {
    require_once __DIR__ . '/lib/Aws/functions.php';
}
if (file_exists(__DIR__ . '/lib/GuzzleHttp/functions.php')) {
    require_once __DIR__ . '/lib/GuzzleHttp/functions.php';
}

class S3StorageBackend extends FileStorageBackend {
    static $desc;

    static $config;
    static $__config;
    private $body;
    private $upload_hash;
    private $upload_hash_final;
    static $version = '2006-03-01';
    static $sig_vers = 'v4';

    static $blocksize = 8192; # Default read size for sockets

    static function setConfig($config) {
        static::$config = $config->getInfo();
        static::$__config = $config;
    }
    function getConfig() {
        return static::$__config;
    }

    function __construct($meta) {
        parent::__construct($meta);
        $secret = Crypto::decrypt(static::$config['secret-access-key'],
            SECRET_SALT, $this->getConfig()->getNamespace());
        $params = S3StoragePluginConfig::buildClientParams(static::$config, $secret);
        $this->client = new S3Client($params);
    }

    function read($bytes=false, $offset=0) {
        try {
            if (!$this->body)
                $this->openReadStream();
            // Reads may be cut short to 8k. Try to read $bytes if at all
            // possible.
            $chunk = '';
            $bytes = $bytes ?: self::getBlockSize();
            while (strlen($chunk) < $bytes) {
                $buf = $this->body->read($bytes - strlen($chunk));
                if (!$buf) break;
                $chunk .= $buf;
            }
            return $chunk;
        }
        catch (Aws\S3\Exception\NoSuchKeyException $e) {
            throw new IOException(self::getKey()
                .': Unable to locate file: '.(string)$e);
        }
    }

    function passthru() {
        try {
            while ($block = $this->read())
                print $block;
        }
        catch (Aws\S3\Exception\NoSuchKeyException $e) {
            throw new IOException(self::getKey()
                .': Unable to locate file: '.(string)$e);
        }
    }

    function write($block) {
        if (!$this->body)
            $this->openWriteStream();
        if (!isset($this->upload_hash))
            $this->upload_hash = hash_init('md5');
        hash_update($this->upload_hash, $block);
        return $this->body->write($block);
    }

    function flush() {
        return $this->upload($this->body);
    }

    function upload($filepath) {
        if ($filepath instanceof Stream) {
            $filepath->rewind();
            // Hashing already performed in the ::write() method
        }
        elseif (is_string($filepath)) {
            $this->upload_hash = hash_init('md5');
            hash_update_file($this->upload_hash, $filepath);
            $filepath = fopen($filepath, 'r');
            rewind($filepath);
        }

        try {
            $params = array(
                'ContentType' => $this->meta->getType(),
                'CacheControl' => 'private, max-age=86400',
            );
            if (isset($this->upload_hash))
                $params['Content-MD5'] =
                    $this->upload_hash_final = hash_final($this->upload_hash);

            $info = $this->client->upload(
                static::$config['bucket'],
                self::getKey(true),
                $filepath,
                static::$config['acl'] ?: 'private',
                array('params' => $params)
            );
            return true;
        }
        catch (S3Exception $e) {
            throw new IOException('Unable to upload to S3: '.(string)$e);
        }
        return false;
    }

    // Support MD5 hash via the returned ETag header;
    function getNativeHashAlgos() {
        return array('md5');
    }

    function getHashDigest($algo) {
        if ($algo == 'md5' && isset($this->upload_hash_final))
            return $this->upload_hash_final;

        // Return nothing. The migrater will compute the hash by downloading
        // the object contents
    }

    // Send a redirect when the file is requested locally
    function sendRedirectUrl($disposition='inline', $ttl = false) {
        // expire based on ttl (if given), otherwise expire at midnight
        $now = time();
        $ttl = $ttl ? $now + $ttl : ($now + 86400 - ($now % 86400));
        Http::redirect($this->getSignedRequest(
            $this->client->getCommand('GetObject', [
                'Bucket' => $this->getBucket(),
                'Key'    => self::getKey(),
                'ResponseContentDisposition' => sprintf("%s; %s;",
                    $disposition,
                    Http::getDispositionFilename($this->meta->getName())),
            ]), $ttl)->getUri());
        return true;
    }

    function unlink() {
        $keys = $this->getCandidateKeys();
        $bucket = $this->getBucket();
        $success = false;
        foreach ($keys as $key) {
            try {
                $this->client->deleteObject(array(
                    'Bucket' => $bucket,
                    'Key'    => $key
                ));
                $success = true;
            }
            catch (S3Exception $e) {
                // Ignore if key was not found under one candidate
            }
        }
        return $success || true;
    }

    // Adapted from Aws\S3\StreamWrapper
    /**
     * Create a pre-signed Request for the given S3 command object.
     *
     * @param Aws\CommandInterface          $command Command to create a pre-signed
     *                                               URL for.
     * @param int|string|\DateTimeInterface $expires The time at which the URL should
     *                                               expire. This can be a Unix
     *                                               timestamp, a PHP DateTime object,
     *                                               or a string that can be evaluated
     *                                               by strtotime().
     *
     * @return RequestInterface
     */
    protected function getSignedRequest($command, $expires=0)
    {
        return $this->client->createPresignedRequest($command, $expires ?: '+30 minutes');
    }

    /**
     * Initialize the stream wrapper for a read only stream
     *
     * @return bool
     */
    protected function openReadStream() {
        $this->getBody(true);
        return true;
    }

    /**
     * Initialize the stream wrapper for a read/write stream
     */
    protected function openWriteStream() {
        $this->body = new Stream(fopen('php://temp', 'r+'));
    }

    /**
     * Get bucket name from saved file attrs or fallback to current config
     */
    function getBucket() {
        $attrs = JsonDataParser::parse($this->meta->getAttrs());
        return ($attrs && isset($attrs['bucket']) && $attrs['bucket'])
            ? $attrs['bucket']
            : static::$config['bucket'];
    }

    /**
     * Candidate S3 keys to try when retrieving or operating on a file.
     * Handles key migration, with/without folder prefix, and trim.
     */
    function getCandidateKeys($create=false) {
        if ($create)
            return array(self::getKey(true));

        $rawKey = $this->meta->getKey();
        $keys = array();

        // Primary key from saved file attrs
        $primaryKey = $this->getKey(false);
        if ($primaryKey)
            $keys[] = $primaryKey;

        // Candidate using current configured plugin folder
        $configFolder = trim(static::$config['folder'] ?? '', '/');
        if ($configFolder)
            $keys[] = sprintf('%s/%s', $configFolder, $rawKey);

        // Candidate at root (no folder prefix)
        $keys[] = $rawKey;

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * Check candidates in Wasabi/S3 to locate existing key
     */
    function resolveExistingKey() {
        $candidateKeys = $this->getCandidateKeys();
        if (count($candidateKeys) <= 1)
            return $candidateKeys[0] ?? $this->meta->getKey();

        $bucket = $this->getBucket();
        foreach ($candidateKeys as $key) {
            try {
                if ($this->client->doesObjectExist($bucket, $key))
                    return $key;
            } catch (Exception $e) {
                continue;
            }
        }

        return $candidateKeys[0];
    }

    protected function getBody($stream=false) {
        $candidateKeys = $this->getCandidateKeys();
        $bucket = $this->getBucket();
        $lastException = null;

        foreach ($candidateKeys as $key) {
            try {
                $params = array(
                    'Bucket' => $bucket,
                    'Key'    => $key,
                );

                $command = $this->client->getCommand('GetObject', $params);
                $command['@http']['stream'] = $stream;
                $result = $this->client->execute($command);
                $this->body = $result['Body'];
                return $this->body;
            } catch (Aws\S3\Exception\NoSuchKeyException $e) {
                $lastException = $e;
            } catch (Exception $e) {
                $lastException = $e;
            }
        }

        if ($lastException)
            throw $lastException;

        return $this->body;
    }

    function getKey($create=false) {
        $attrs = $create ? self::getAttrs() : $this->meta->getAttrs();
        $attrs = JsonDataParser::parse($attrs);

        $folder = '';
        if ($attrs && isset($attrs['folder']) && $attrs['folder']) {
            $folder = trim($attrs['folder'], '/');
        }

        $rawKey = $this->meta->getKey();
        return $folder ? sprintf('%s/%s', $folder, $rawKey) : $rawKey;
    }

    function getAttrs() {
        $bucket = static::$config['bucket'];
        $folder = trim(static::$config['folder'] ?? '', '/');
        $attr = JsonDataEncoder::encode(array('bucket' => $bucket, 'folder' => $folder));

        return $attr;
    }
}

require_once 'config.php';

class S3StoragePlugin extends Plugin {
    var $config_class = 'S3StoragePluginConfig';

    function isMultiInstance() {
        return false;
    }

    function bootstrap() {
        if (!class_exists('Aws\S3\S3Client')) {
            // AWS SDK not loaded (plugin unhydrated or missing lib/)
            return false;
        }

        require_once 'storage.php';

        //TODO: This needs to target a specific instance
        $bucketPath = sprintf('%s%s', $this->getConfig()->get('bucket'),
            $this->getConfig()->get('folder') ? '/'. $this->getConfig()->get('folder') : '');
        S3StorageBackend::setConfig($this->getConfig());
        $provider = $this->getConfig()->get('provider') ?: 'aws';
        $presets = S3StoragePluginConfig::getProviderPresets();
        $label = isset($presets[$provider]) ? $presets[$provider]['label'] : 'S3';
        S3StorageBackend::$desc = sprintf('%s (%s)', $label, $bucketPath);
        FileStorageBackend::register('3', 'S3StorageBackend');
    }
}
