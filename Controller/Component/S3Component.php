<?php

App::import('Vendor','AWS', array('file' => 'AWS/sdk.class.php'));

class S3Component extends Component {
  private $s3 = null;
  private $region = AmazonS3::REGION_EU_W1;
  private $bucket = 'sellbox';

  public function getS3() {
    if (is_null($this->s3)) {
      $this->s3 = new AmazonS3();
    }
    return $this->s3;
  }

  public function getBuckets() {
    return $this->getS3()->get_bucket_list();
  }

  public function createBucket($name = null) {
    return $this->getS3()->create_bucket($name, $this->region);
  }

  public function readBucket($name) {

  }

  public function deleteBucket($name) {
    return $this->getS3()->delete_bucket($name);
  }

  public function upload($file_name, $file_path) {
    return $this->getS3()->create_object($this->bucket, $file_name, array(
      'fileUpload' => $file_path,
      'acl' => AmazonS3::ACL_PRIVATE,
      'storage' => AmazonS3::STORAGE_REDUCED,
    ));
  }

  public function copy($source, $dest) {
    $result = false;

    $opt = array(
      'acl' => AmazonS3::ACL_PUBLIC,
      'storage' => AmazonS3::STORAGE_REDUCED,
    );

    $response = $this->getS3()->copy_object(
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
    $response = $this->getS3()->create_object_expiration_config($this->bucket, compact('rules'));

    return ($response->status == 200);
  }

  public function getExpirations() {
    $response = $this->getS3()->get_object_expiration_config($this->bucket);

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
    $response = $this->getS3()->delete_object_expiration_config($this->bucket);
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
      $result = $this->getS3()->get_object_list($this->bucket, $opt);
    } else {
      $opt = compact('prefix', 'delimiter', 'marker');
      $response = $this->getS3()->list_objects($this->bucket, $opt);

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

  public function url($file, $expires = null) {

    if (!is_null($expires)) {
      $expires = gmdate(DATE_RFC2822, strtotime($expires));
      $opt = array('response' => compact('expires'));
    } else {
      $opt = null;
    }

    $response = $this->getS3()->get_object_url($this->bucket, $file, 0, $opt);

    print '<pre>';
    print_r($response);
    print '</pre>';
    die('print_r');
  }

  public function delete($file) {
  }
}