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
    $rules = $this->getExpirations();
    $rules[] = compact('prefix', 'expiration');
    return $this->setExpirations($rules);
  }

  public function setExpirations($rules) {
    foreach ($rules as &$rule) {
      if (empty($rule['prefix']) || empty($rule['expiration'])) {
        throw new CakeException('Rule has to have `prefi` and `expiration` field.');
      }
      $rule['expiration'] = array('days' => $rule['expiration']);
    }
    $response = $this->getS3()->create_object_expiration_config($this->bucket, compact('rules'));

    return ($response->status == 200);
  }

  public function getExpirations() {
    $response = $this->getS3()->get_object_expiration_config($this->bucket);

    $result = array();


    print_r($response->body->Rule->Prefix->to_string());
    $response->body->Rule->next();
    print_r($response->body->Rule->Prefix->to_string());
    die('stop');

    if (is_array($response->body->Rule)) {
      foreach ($response->body->Rule as $rule) {
        $result[] = array(
          'prefix' => $rule->Prefix->to_string(),
          'expiration' => $rule->Expiration->Days->to_string(),
        );
      }
    } elseif (!empty($response->body->Rule)) {

    }

    print '<pre>';
    print_r($result);
    print '</pre>';
    die('print_r');


    return $result;
  }

  public function files($prefix = '', $include_dirs = true, $marker = 0) {
    $delimiter = '/';
    $prefix .= $delimiter;
    $opt = compact('prefix', 'delimiter', 'marker');

    if (!$include_dirs) {
      $result = $this->getS3()->get_object_list($this->bucket, $opt);
    } else {
      $response = $this->getS3()->list_objects($this->bucket, $opt);

      $result = array('dirs' => array(), 'files' => array());

      if (is_array($response->body->Contents)) {
        foreach ($response->body->Contents as $file) {
          $result['files'][] = $file->Key->to_string();
        }
      } elseif (!empty($response->body->Contents)) {
        $result['files'][] = $response->body->Contents->Key->to_string();
      }

      if (is_array($response->body->CommonPrefixes)) {
        foreach ($response->body->CommonPrefixes as $dir) {
          $result['dirs'][] = $dir->Prefix->to_string();
        }
      } elseif (!empty($response->body->CommonPrefixes)) {
        $result['dirs'][] = $response->body->CommonPrefixes->Prefix->to_string();
      }
    }

    return $result;
  }

  public function url($file) {
    // $this->getS3()->
  }

  public function delete($file) {
  }
}