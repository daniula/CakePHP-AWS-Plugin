<?php

App::import('Vendor','AWS', array('file' => 'AWS/sdk.class.php'));

class S3Component extends Component {
  private $service;
  private $region = AmazonS3::REGION_EU_W1;
  private $bucket = 'sellbox';

  public function __construct(ComponentCollection $collection, $settings = array()) {
    parent::__construct($collection, $settings);
    $this->service = new AmazonS3();
    $this->service->ssl_verification = false;
  }

  public function getBuckets() {
    return $this->service->get_bucket_list();
  }

  public function createBucket($name = null) {
    return $this->service->create_bucket($name, $this->region);
  }

  public function bucket($bucket = null) {
    if (!is_null($bucket)) {
      $this->bucket = $bucket;
    }
    return $this->bucket;
  }

  public function deleteBucket($name) {
    return $this->service->delete_bucket($name);
  }

  public function upload($file_name, $file_path, $public = false) {
    $result = $this->service->create_object($this->bucket, $file_name, array(
      'fileUpload' => $file_path,
      'acl' => $public ? AmazonS3::ACL_PUBLIC : AmazonS3::ACL_PRIVATE,
      'storage' => AmazonS3::STORAGE_REDUCED,
    ));
    return $this->url($file_name);
  }

  public function copy($source, $dest) {
    $result = false;

    $opt = array(
      'acl' => AmazonS3::ACL_PUBLIC,
      'storage' => AmazonS3::STORAGE_REDUCED,
    );

    $response = $this->service->copy_object(
      array('bucket' => $this->bucket, 'filename' => $source),
      array('bucket' => $this->bucket, 'filename' => $dest),
      $opt
    );

    if ($response->status == 200) {
      $result = $response->header['x-aws-request-url'];
    }

    return $result;
  }

  public function expiration($prefix, $expiration = 1) {
    if (!preg_match('`/$`', $prefix)) {
      $prefix .= '/';
    }
    $rules = $this->getExpirations();
    $rules[] = compact('prefix', 'expiration');
    return $this->setExpirations($rules);
  }

  public function setExpirations($rules) {
    foreach ($rules as &$rule) {
      if (empty($rule['prefix']) || empty($rule['expiration'])) {
        throw new Exception('Rule has to have `prefix` and `expiration` field.');
      }
      $rule['expiration'] = array('days' => $rule['expiration']);
    }
    $response = $this->service->create_object_expiration_config($this->bucket, compact('rules'));

    return ($response->status == 200);
  }

  public function getExpirations() {
    $response = $this->service->get_object_expiration_config($this->bucket);

    $result = array();

    if ($response->body->Rule()) {
      foreach ($response->body->Rule()->getArrayCopy() as $rule) {
        $result[] = array(
          'prefix' => $rule->Prefix->to_string(),
          'expiration' => $rule->Expiration->Days->to_string(),
        );
      }
    }

    return $result;
  }

  public function cleanExpirations() {
    $response = $this->service->delete_object_expiration_config($this->bucket);
    return ($response->status == 204);
  }

  public function files($prefix = '', $tree = true, $marker = 0) {
    if (is_bool($prefix)) {
      if ($tree !== true) {
        $marker = $tree;
      }
      $tree = $prefix;
      $prefix = '';
    }

    $delimiter = '/';
    if ($prefix !== '') {
      $prefix .= $delimiter;
    }

    if (!$tree) {
      $opt = compact('prefix', 'marker');
      $result = $this->service->get_object_list($this->bucket, $opt);
    } else {
      $opt = compact('prefix', 'delimiter', 'marker');
      $response = $this->service->list_objects($this->bucket, $opt);

      $result = array('dirs' => array(), 'files' => array());

      if ($response->body->Contents()) {
        foreach ($response->body->Contents()->getArrayCopy() as $file) {
          $result['files'][] = $file->Key->to_string();
        }
      }

      if ($response->body->CommonPrefixes()) {
        foreach ($response->body->CommonPrefixes()->getArrayCopy() as $dir) {
          $result['dirs'][] = $dir->Prefix->to_string();
        }
      }
    }

    return $result;
  }

  public function url($file, $expires = 0) {
    return $this->service->get_object_url($this->bucket, $file, $expires);
  }

  public function delete($files) {
    $result = $objects = array();
    $files = is_array($files) ? $files : array($files);

    foreach ($files as $file) {
      $file = parse_url($file);
      $objects[] = array('key' => substr($file['path'], 1));
    }

    $response = $this->service->delete_objects($this->bucket, compact('objects'));

    if ($response->body->Deleted()) {
      foreach ($response->body->Deleted()->getArrayCopy() as $deleted) {
        $result[] = $deleted->Key->to_string();
      }
    }

    return $result;
  }
}