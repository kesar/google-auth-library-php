<?php
/*
 * Copyright 2015 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Auth;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

/**
 * CredentialsLoader contains the behaviour used to locate and find default
 * credentials files on the file system.
 */
class CredentialsLoader implements FetchAuthTokenInterface
{
  const TOKEN_CREDENTIAL_URI = 'https://www.googleapis.com/oauth2/v3/token';
  const ENV_VAR = 'GOOGLE_APPLICATION_CREDENTIALS';
  const WELL_KNOWN_PATH = 'gcloud/application_default_credentials.json';
  const AUTH_METADATA_KEY = 'Authorization';

  private static function unableToReadEnv($cause)
  {
    $msg = 'Unable to read the credential file specified by ';
    $msg .= ' GOOGLE_APPLICATION_CREDENTIALS: ';
    $msg .= $cause;
    return $msg;
  }

  private static function isOnWindows()
  {
    return strtoupper(substr(php_uname('s'), 0, 3)) === 'WIN';
  }

  /**
   * The OAuth2 instance used to conduct authorization.
   */
  protected $auth;

  /**
   * Create a credentials instance from the path specified in the environment.
   *
   * Creates a credentials instance from the path specified in the environment
   * variable GOOGLE_APPLICATION_CREDENTIALS. Return null if
   * GOOGLE_APPLICATION_CREDENTIALS is not specified.
   *
   * @param string|array scope the scope of the access request, expressed
   *   either as an Array or as a space-delimited String.
   *
   * @return a Credentials instance | null
   */
  public static function fromEnv($scope = null)
  {
    $path = getenv(self::ENV_VAR);
    if (empty($path)) {
      return null;
    }
    if (!file_exists($path)) {
      $cause = "file " . $path . " does not exist";
      throw new \DomainException(self::unableToReadEnv($cause));
    }
    $keyStream = Stream::factory(file_get_contents($path));
    return static::makeCredentials($scope, $keyStream);
  }

  /**
   * Create a credentials instance from a well known path.
   *
   * The well known path is OS dependent:
   * - windows: %APPDATA%/gcloud/application_default_credentials.json
   * - others: $HOME/.config/gcloud/application_default_credentials.json
   *
   * If the file does not exists, this returns null.
   *
   * @param string|array scope the scope of the access request, expressed
   *   either as an Array or as a space-delimited String.
   *
   * @return a Credentials instance | null
   */
  public static function fromWellKnownFile($scope = null)
  {
    $rootEnv = self::isOnWindows() ? 'APPDATA' : 'HOME';
    $root = getenv($rootEnv);
    $path = join(DIRECTORY_SEPARATOR, [$root, self::WELL_KNOWN_PATH]);
    if (!file_exists($path)) {
      return null;
    }
    $keyStream = Stream::factory(file_get_contents($path));
    return static::makeCredentials($scope, $keyStream);
  }

 /**
  * Implements FetchAuthTokenInterface#fetchAuthToken.
  */
  public function fetchAuthToken(ClientInterface $client = null)
  {
    return $this->auth->fetchAuthToken($client);
  }

 /**
  * Implements FetchAuthTokenInterface#getCacheKey.
  */
  public function getCacheKey()
  {
    return $this->auth->getCacheKey();
  }


  /**
   * export a callback function which updates runtime metadata 
   *
   * @return an updateMetadata function 
   */
  public function getUpdateMetadataFunc()
  {
    return array($this, 'updateMetadata');
  }

  /**
   * Updates a_hash with the authorization token
   *
   * @param $a_hash array metadata hashmap
   * @param $client optional client interface
   *
   * @return array updated metadata hashmap
   */
  public function updateMetadata($a_hash,
                                 ClientInterface $client = null)
  {
    $result = $this->fetchAuthToken($client);
    if (!isset($result['access_token'])) {
      return $a_hash;
    }
    $a_copy = $a_hash;
    $a_copy[self::AUTH_METADATA_KEY] = array('Bearer ' . $result['access_token']);
    return $a_copy;
  }
}
