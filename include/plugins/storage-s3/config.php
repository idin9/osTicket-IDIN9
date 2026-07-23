<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class S3StoragePluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    static function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-ldap');
    }

    /**
     * Provider presets. The AWS PHP SDK speaks to any S3-compatible
     * object storage by setting a custom `endpoint` (and sometimes
     * `use_path_style_endpoint`). Each preset supplies a default region,
     * an endpoint template (with {region} substitution) and whether
     * path-style addressing is required by default.
     *
     * endpoint === null means "user must supply one" (requiresEndpoint)
     * for providers whose endpoint embeds an account id or is self-hosted.
     */
    static function getProviderPresets() {
        return array(
            'aws' => array(
                'label' => 'Amazon S3',
                'endpoint' => null,            // SDK default (virtual-hosted)
                'region' => 'us-east-1',
                'path' => false,
                'requiresEndpoint' => false,
            ),
            'wasabi' => array(
                'label' => 'Wasabi',
                'endpoint' => 'https://s3.{region}.wasabisys.com',
                'region' => 'us-east-1',
                'path' => true,
                'requiresEndpoint' => false,
            ),
            'google' => array(
                'label' => 'Google Cloud Storage',
                'endpoint' => 'https://storage.googleapis.com',
                'region' => 'auto',
                'path' => false,
                'requiresEndpoint' => false,
            ),
            'digitalocean' => array(
                'label' => 'DigitalOcean Spaces',
                'endpoint' => 'https://{region}.digitaloceanspaces.com',
                'region' => 'nyc3',
                'path' => false,
                'requiresEndpoint' => false,
            ),
            'backblaze' => array(
                'label' => 'Backblaze B2',
                'endpoint' => 'https://s3.{region}.backblazeb2.com',
                'region' => 'us-west-001',
                'path' => true,
                'requiresEndpoint' => false,
            ),
            'cloudflare' => array(
                'label' => 'Cloudflare R2',
                // Endpoint embeds the account id — user must supply it.
                'endpoint' => null,
                'region' => 'auto',
                'path' => false,
                'requiresEndpoint' => true,
            ),
            'minio' => array(
                'label' => 'MinIO / Self-hosted',
                'endpoint' => null,
                'region' => 'us-east-1',
                'path' => true,
                'requiresEndpoint' => true,
            ),
            'custom' => array(
                'label' => 'Custom S3-compatible',
                'endpoint' => null,
                'region' => 'us-east-1',
                'path' => true,
                'requiresEndpoint' => true,
            ),
        );
    }

    /**
     * Build an Aws\S3\S3Client-ready parameter array for the configured
     * provider. Shared by pre_save() validation and the storage backend.
     *
     * @param $config  array  Plugin config values (provider, keys, region,
     *                        endpoint, path-style, ...)
     * @param $secret  string Decrypted secret access key
     */
    static function buildClientParams($config, $secret) {
        $presets = self::getProviderPresets();
        $provider = $config['provider'] ?: 'aws';
        $preset = isset($presets[$provider]) ? $presets[$provider] : $presets['custom'];

        // Resolve region. AWS uses the dedicated dropdown; other providers
        // use the free-text region field, falling back to the preset.
        if ($provider === 'aws')
            $region = $config['aws-region'] ?: $preset['region'];
        else
            $region = $config['region'] ?: $preset['region'];

        $params = array(
            'credentials' => array(
                'key' => $config['aws-key-id'],
                'secret' => $secret,
            ),
            'region' => $region,
            'version' => '2006-03-01',
            'signature_version' => 'v4',
        );

        // Resolve endpoint: explicit value wins, otherwise derive from the
        // preset template. AWS leaves it unset so the SDK uses its default.
        $endpoint = trim((string) $config['endpoint']);
        if (!$endpoint && $preset['endpoint'])
            $endpoint = str_replace('{region}', $region, $preset['endpoint']);
        if ($endpoint)
            $params['endpoint'] = $endpoint;

        // Path-style addressing. The checkbox only ever forces it ON; the
        // preset decides the default for providers that require it.
        if (!empty($config['path-style']) || !empty($preset['path']))
            $params['use_path_style_endpoint'] = true;

        return $params;
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'provider' => new ChoiceField(array(
                'label' => $__('Storage Provider'),
                'choices' => array(
                    'aws' => $__('Amazon S3'),
                    'wasabi' => $__('Wasabi'),
                    'google' => $__('Google Cloud Storage (S3 interoperable)'),
                    'digitalocean' => $__('DigitalOcean Spaces'),
                    'backblaze' => $__('Backblaze B2'),
                    'cloudflare' => $__('Cloudflare R2'),
                    'minio' => $__('MinIO / Self-hosted'),
                    'custom' => $__('Custom S3-compatible'),
                ),
                'default' => 'aws',
                'hint' => $__('Supports Amazon S3, Wasabi, Google Cloud Storage, DigitalOcean Spaces, Backblaze B2, Cloudflare R2, MinIO, or any S3-compatible provider.'),
            )),
            'bucket' => new TextboxField(array(
                'label' => $__('S3 Bucket'),
                'configuration' => array('size'=>40),
            )),
            'folder' => new TextboxField(array(
                'label' => $__('S3 Folder Path'),
                'configuration' => array('size'=>40),
            )),
            'endpoint' => new TextboxField(array(
                'label' => $__('Custom Endpoint URL'),
                'configuration' => array('size'=>40, 'length'=>255),
                'hint' => $__('Leave blank for AWS and other known providers (auto-derived). Required for Cloudflare R2 (https://ACCOUNT_ID.r2.cloudflarestorage.com), MinIO, and Custom.'),
            )),
            'aws-region' => new ChoiceField(array(
                'label' => $__('AWS Region (Amazon S3 only)'),
                'choices' => array(
                    '' => 'US Standard',
                    'us-east-1' => 'US East (N. Virginia)',
                    'us-east-2' => 'US East (Ohio)',
                    'us-west-1' => 'US West (N. California)',
                    'us-west-2' => 'US West (Oregon)',
                    'af-south-1' => 'Africa (Cape Town)',
                    'ap-east-1' => 'Asia Pacific (Hong Kong)',
                    'ap-south-1' => 'Asia Pacific (Mumbai)',
                    'ap-south-2' => 'Asia Pacific (Hyderabad)',
                    'ap-southeast-3' => 'Asia Pacific (Jakarta)',
                    'ap-southeast-4' => 'Asia Pacific (Melbourne)',
                    'ap-northeast-3' => 'Asia Pacific (Osaka)',
                    'ap-northeast-2' => 'Asia Pacific (Seoul)',
                    'ap-southeast-1' => 'Asia Pacific (Singapore)',
                    'ap-southeast-2' => 'Asia Pacific (Sydney)',
                    'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                    'ca-central-1' => 'Canada (Central)',
                    'ca-west-1' => 'Canada West (Calgary)',
                    'cn-north-1' => 'China (Beijing)',
                    'cn-northwest-1' => 'China (Ningxia)',
                    'eu-central-1' => 'Europe (Frankfurt)',
                    'eu-west-1' => 'Europe (Ireland)',
                    'eu-west-2' => 'Europe (London)',
                    'eu-south-1' => 'Europe (Milan)',
                    'eu-west-3' => 'Europe (Paris)',
                    'eu-south-2' => 'Europe (Spain)',
                    'eu-north-1' => 'Europe (Stockholm)',
                    'eu-central-2' => 'Europe (Zurich)',
                    'il-central-1' => 'Israel (Tel Aviv)',
                    'sa-east-1' => 'South America (São Paulo)',
                    'me-south-1' => 'Middle East (Bahrain)',
                    'me-central-1' => 'Middle East (UAE)',
                    'us-gov-east-1' => 'AWS GovCloud (US-East)',
                    'us-gov-west-1' => 'AWS GovCloud (US-West)',
                ),
                'default' => '',
            )),
            'region' => new TextboxField(array(
                'label' => $__('Region (non-AWS providers)'),
                'configuration' => array('size'=>20, 'length'=>64),
                'hint' => $__('e.g. us-east-1 (Wasabi/MinIO), nyc3 (DigitalOcean), us-west-001 (Backblaze), auto (Google/R2)'),
            )),
            'path-style' => new BooleanField(array(
                'label' => $__('Use path-style endpoint'),
                'hint' => $__('Put the bucket name in the URL path. Auto-enabled for Wasabi, Backblaze B2, and MinIO; check to force for other providers.'),
            )),
            'acl' => new ChoiceField(array(
                'label' => $__('Default ACL for Attachments'),
                'choices' => array(
                    '' => $__('Use Bucket Default'),
                    'private' => $__('Private'),
                    'public-read' => $__('Public Read'),
                    'public-read-write' => $__('Public Read and Write'),
                    'authenticated-read' => $__('Read for AWS authenticated Users'),
                    'bucket-owner-read' => $__('Read for Bucket Owners'),
                    'bucket-owner-full-control' => $__('Full Control for Bucket Owners'),
                ),
                'default' => '',
            )),

            'access-info' => new SectionBreakField(array(
                'label' => $__('Access Information'),
            )),
            'aws-key-id' => new TextboxField(array(
                'required' => true,
                'configuration'=>array('length'=>64, 'size'=>40),
                'label' => $__('Access Key ID'),
            )),
            'secret-access-key' => new TextboxField(array(
                'widget' => 'PasswordWidget',
                'required' => false,
                'configuration'=>array('length'=>64, 'size'=>40),
                'label' => $__('Secret Access Key'),
                'hint' => $__('For Google Cloud Storage use an HMAC interoperability key.'),
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        list($__, $_N) = self::translate();

        // Resolve the secret to validate against: prefer the freshly entered
        // one, otherwise fall back to the stored (encrypted) value.
        $secret = $config['secret-access-key']
            ?: Crypto::decrypt($this->get('secret-access-key'), SECRET_SALT,
                    $this->getNamespace());

        if (!$secret) {
            $this->getForm()->getField('secret-access-key')->addError(
                $__('Secret access key is required'));
        }

        // Validate provider-specific requirements (endpoint mandatory for
        // Cloudflare R2, MinIO and Custom providers).
        $presets = self::getProviderPresets();
        $provider = $config['provider'] ?: 'aws';
        $preset = isset($presets[$provider]) ? $presets[$provider] : $presets['custom'];
        if (!empty($preset['requiresEndpoint']) && !trim((string) $config['endpoint'])) {
            $this->getForm()->getField('endpoint')->addError(
                $__('Endpoint URL is required for this provider'));
        }

        // Only attempt a connection if the basics are satisfied.
        if ($secret && !$errors) {
            $s3 = new Aws\S3\S3Client(self::buildClientParams($config, $secret));

            try {
                $s3->headBucket(array('Bucket'=>$config['bucket']));
            }
            catch (Aws\S3\Exception\AccessDeniedException $e) {
                $errors['err'] = sprintf(
                    /* The %s token will become an upstream error message */
                    $__('User does not have access to this bucket: %s'), (string)$e);
            }
            catch (Aws\S3\Exception\NoSuchBucketException $e) {
                $this->getForm()->getField('bucket')->addError(
                    $__('Bucket does not exist'));
            }
            catch (Exception $e) {
                // Endpoint unreachable, DNS failure, signature mismatch, etc.
                $errors['err'] = sprintf(
                    $__('Unable to connect to storage provider: %s'),
                    $e->getMessage());
            }
        }

        if (!$errors && $config['secret-access-key'])
            $config['secret-access-key'] = Crypto::encrypt($config['secret-access-key'],
                SECRET_SALT, $this->getNamespace());
        else
            $config['secret-access-key'] = $this->get('secret-access-key');

        return true;
    }
}
