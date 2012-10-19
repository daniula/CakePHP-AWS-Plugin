<?php

App::import('Vendor','AWS', array('file' => 'AWS/sdk.class.php'));

class S3Component extends Component {
  private $s3 = null;
  private $region = AmazonS3::REGION_EU_W1;

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

  public function upload($bucket, $file_name, $file_path) {

  }

  public function delete($bucket, $file_name = null) {
  }
}