<?php

App::import('Vendor','AWS', array('file' => 'AWS/sdk.class.php'));

class CloudFrontComponent extends Component {
  private $service;

  public function initialize($controller) {
    $this->service = new AmazonCloudFront();
  }

  public function distributions($id = null, $return = 'all') {
    $result = false;

    if (is_null($id)) {
      $response = $this->service->list_distributions();

      if ($response->body->DistributionSummary()) {
          $result = $response->body->DistributionSummary()->getArrayCopy();
      }
    } else {
      $result = $this->service->get_distribution_info($id)->body;
    }

    return $result;
  }

  private function pluginRoot() {
    return dirname(dirname(dirname(__FILE__)));
  }

  public function loadKey() {
    $configPath = $this->pluginRoot().DS.'Config'.DS.'security.php';

    if (file_exists($configPath)) {
      include($configPath);
    } else {
      throw new CakeException('[plugin_path]/Confg/security.php is missing.');
    }

    $private_key = Configure::read('AWS.CloudFront.private_key');
    if (!$private_key) {
      throw new CakeException('Configuration value AWS.CloudFront.private_key is missing.');
    }

    if (!preg_match('/^pk-(.{20})\.pem$/', $private_key, $private_key)) {
      throw new CakeException('Inappropriate format for private_key. It should look like pk-[20 random characters].pem');
    }

    if (!file_exists($this->pluginRoot().DS.'Config'.DS.$private_key[0])) {
      throw new CakeException($private_key[0].' file with RSA key is missing');
    }

    $key = file_get_contents($this->pluginRoot().DS.'Config'.DS.$private_key[0]);
    if (empty($key)) {
      throw new CakeException($private_key[0].' is empty');
    }

    $this->service->set_keypair_id($private_key[1])->set_private_key($key);

    return true;
  }

  public function getUrl($file, $expires) {
    $this->loadKey();

    list($cloudfront) = $this->distributions();
    // $hostname = $cloudfront->CNAME->to_string();
    $hostname = $cloudfront->DomainName->to_string();
    $response = $this->service->get_private_object_url($hostname, $file, $expires);

    return $response;
  }

}