<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is licensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 *       the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attributes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2012 (or current year) Dmitri Snytkine
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\Mongo;

use \Lampcms\DevException;

/**
 * Wrapped class for working with
 * php's MongoDB classes
 *
 * @author Dmitri Snytkine
 *
 */
class DB extends \Lampcms\LampcmsObject
{

    //protected static $oMongo;


    /**
     * Mongo connection resource
     *
     * @var object of type \Mongo
     */
    protected $conn;


    /**
     * Object MongoDB
     *
     * @var object of type \MongoDB
     */
    protected $db;


    /**
     * Name of database
     *
     * @var string
     */
    protected $dbname;

    /**
     *
     * Config/Ini object
     *
     * @var object Lampcms\Config\Ini
     */
    protected $Ini;


    /**
     * Extra options used during insert and save
     *
     * @var array
     */
    protected $aInsertOption = array('safe' => true);

    /**
     * Prefix for collection names
     * If set to any non-empty string then
     * ALL collections will be prefixed
     * with this string. This option
     * allows to override default collection names
     * used in this program in case the existing
     * database already has collections with same names
     * as in the program.
     *
     * @var string
     */
    protected $prefix = "";


    public function __construct(\Lampcms\Config\Ini $Ini)
    {

        if (!\extension_loaded('mongo')) {
            throw new \OutOfBoundsException('Unable to use this program because PHP mongo extension not loaded. Make sure your php has mongo extension enabled. Exiting');
        }

        $this->Ini = $Ini;
        $aOptions  = array('connect' => true);
        $aConfig   = $Ini->getSection('MONGO');


        $server = $aConfig['server'];
        /**
         * For Unit testing we define
         * MONGO_DBNAME to be LAMPCMS_TEST
         * so that actual database not used during testing
         *
         */
        $this->dbname = (defined('MONGO_DBNAME')) ? constant('MONGO_DBNAME') : $aConfig['db'];

        try {
            /**
             * Need to lower to error reporting level just for
             * this method because Mongo may raise notices
             * that we are not interested in.
             * We only care about actual exceptions
              */
            $ER         = \error_reporting(0);
            $this->conn = new \Mongo($server, $aOptions);
            \error_reporting($ER);

        } catch ( \MongoConnectionException $e ) {
            $err = 'MongoConnectionException caught. Unable to connect to Mongo: ' . $e->getMessage();
            e($err);
            throw new DevException($err);
        }
        catch ( \MongoException $e ) {
            $err = 'MongoException caught. Unable to connect to Mongo: ' . $e->getMessage();
            e($err);
            throw new DevException($err);
        }
        catch ( DevException $e ) {
            $err = 'Unable to connect to Mongo: ' . $e->getMessage();
            e($err);
        }
        catch ( \Exception $e ) {
            /**
             * This will not be a MongoException
             * because mongo connection process will not throw exception,
             * it will raise php error or warning which is then
             * processed by out error handler and turned into DevException
             * So we are getting DevException here but may also
             * get MongoException
             */

            $err = 'Unable to connect to Mongo: ' . $e->getMessage();
            e($err);
            throw new DevException($err);
        }

        if (null === $this->conn) {
            throw new DevException('No connection to MongoDB');
        }


        if (!empty($aConfig['prefix'])) {
            $this->prefix = (string)$aConfig['prefix'];
        }
    }


    /**
     * Getter for $this->dbname
     *
     * @return string name of database used
     */
    public function getDbName()
    {
        return $this->dbname;
    }


    /**
     * Setter for $this->dbname
     *
     * @param $name
     *
     * @throws \InvalidArgumentException
     * @internal param \Lampcms\Mongo\name $string Database name
     *
     * @return object $this
     */
    public function setDbName($name)
    {

        if (!is_string($name)) {
            throw new \InvalidArgumentException('$name must be a string. Was: ' . gettype($name));
        }

        $this->dbname = $name;

        return $this;
    }


    public function __clone()
    {
        throw new DevException('cloning Mongo object is not allowed');
    }


    /**
     * By default pass methods to $this->db (MongoDatabase object)
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        d('Passing call to php MongoDB. Method: ' . $method . ' $args: ' . print_r($args, 1));

        return \call_user_func_array(array($this->getDb(), $method), $args);
    }


    /**
     * Insert array into MongoDB collection
     *
     * @param string $collName name of collection
     *
     * @param array  $aValues  array of data to insert
     *
     * @param mixed  $option   option to pass to mongoCollection->insert()
     *                         this could be bool true for 'safe' but can also be an array
     *
     * @throws \InvalidArgumentException
     * @return mixed false on failure or value of _id of inserted doc
     * which can be MongoId Object or string or int, depending if
     * you included value of _id in $aValues or let Mongo generate one
     * By default mongo generates the unique value and it's an object
     * of type MongoId
     */
    public function insertData($collName, array $aValues, $option = array('safe' => true))
    {
        d('$option: ' . var_export($option, true));

        if (!is_array($option)) {
            e('Second param passed to insertData must now be an array!. Was: ' . var_export($option, true));
        }

        if (!is_string($collName)) {
            throw new \InvalidArgumentException('$name must be a string. Was: ' . gettype($collName));
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $collName)) {
            throw new \InvalidArgumentException('Invalid collection name: ' . $collName . ' Colletion name can only contain alphanumeric chars and underscores');
        }

        try {
            $coll = $this->getCollection($collName);

            $ret = $coll->insert($aValues, $option);
        } catch ( \MongoException $e ) {
            e('Insert() failed: ' . $e->getMessage() . ' values: ' . print_r($aValues, 1) . ' backtrace: ' . $e->getTraceAsString());

            return false;
        }

        return (!empty($aValues['_id'])) ? $aValues['_id'] : false;

    }


    /**
     * @todo     this is dangerous!
     *           it will replace record with arrValues and will not keep
     *           any existing values
     *           the better way would be to use $set operator
     *           MUST make sure tha arrValues include new values AND CURRENT
     *           values that don't have to change. For example, if you
     *           only updating 'lastName', make sure arrValues also
     *           includes 'firstName' with the current value, otherwise
     *           the new object will have only the lastName
     *
     * @param string $collName
     * @param array  $arrValues
     * @param string $whereCol
     * @param string $whereVal
     *
     * @throws \InvalidArgumentException
     * @internal param string $strErr2 can be passed to be included in logging
     * @return bool
     */
    public function updateCollection($collName, array $arrValues, $whereCol, $whereVal)
    {
        if (!is_string($collName)) {
            throw new \InvalidArgumentException('$name must be a string. Was: ' . gettype($collName));
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $collName)) {
            throw new \InvalidArgumentException('Invalid collection name: ' . $collName . ' Collection name can only contain alphanumeric chars and underscores');
        }

        $whereCol = \filter_var($whereCol, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        $whereCol = \str_replace(';', '', $whereCol);
        $whereCol = \addslashes($whereCol);

        $ret = false;

        $coll = $this->getDb()->selectCollection($collName);
        try {
            $ret = $coll->update(array($whereCol => $whereVal), $arrValues, array('fsync' => true));
        } catch ( \MongoException $e ) {
            e('Unable to update mongo collection ' . $collName . ' ' . $e->getMessage());
        }

        return $ret;
    }


    /**
     *
     * Update collection but do not replace
     * entire document, instead
     * only update the columns
     * that are present in $values
     *
     * @param string $collName
     * @param array  $values
     * @param array  $cond the condition to match on
     * @param array  $options
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function update($collName, array $values, array $cond, array $options = array())
    {
        if (!is_string($collName)) {
            throw new \InvalidArgumentException('$name must be a string. Was: ' . gettype($collName));
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $collName)) {
            throw new \InvalidArgumentException('Invalid collection name: ' . $collName . ' Colletion name can only contain alphanumeric chars and underscores');
        }

        $ret = false;

        if (empty($options['fsync']) && empty($options['safe'])) {
            $options['fsync'] = true;
        }

        $options['multiple'] = true;

        $coll = $this->getDb()->selectCollection($collName);
        try {
            $ret = $coll->update($cond, array('$set' => $values), $options);
        } catch ( \MongoCursorException $e ) {
            e('Unable to update mongo collection ' . $collName . ' ' . $e->getMessage());
        }

        return $ret;
    }


    /**
     *
     * Update collection but do not replace
     * entire document, instead
     * only update the columns
     * that are present in $values
     *
     * @param string $collName
     * @param array  $values
     * @param array  $cond the condition to match on
     *
     * @internal param array $options
     * @return bool
     */
    public function upsert($collName, array $values, array $cond)
    {

        return $this->update($collName, $values, $cond, array('fsync' => true, 'upsert' => true));

        return $ret;
    }


    /**
     * Getter for $this->db
     *
     * @return object of type \MongoDB
     */
    public function getDb()
    {
        return $this->conn->selectDB($this->dbname);
    }

    /**
     * Flush (commit) all changes to disk
     * Usually you would run this method
     * after performing multiple save() or insert
     * operations without the fsync => true option
     *
     */
    public function flush()
    {
        return $this->getDb()->command(array("getlasterror" => 1, "fsync" => 1));
    }


    /**
     * Return Mongo Collection object from default database
     * if you need to select collection from different database, then
     * you should use getMongo->selectCollection($db, $collName)
     *
     * @param string $collName name of collection
     *
     * @throws \InvalidArgumentException
     * @return object of type MongoCollection
     */
    public function getCollection($collName)
    {
        if (!\is_string($collName)) {
            throw new \InvalidArgumentException('Param $collName must be a string. was: ' . gettype($collName));
        }

        d('$collName: ' . $collName);

        $coll = defined('Lampcms\Mongo\\' . $collName) ? \constant('Lampcms\Mongo\\' . $collName) : $this->Ini->MYCOLLECTIONS->{$collName};

        return $this->conn->selectCollection($this->dbname, $this->prefix . $coll);
    }


    /**
     * Getter for prefix
     *
     * @return string by default prefix is an empty String
     * which is perfectly fine
     *
     */
    public function getPrefix()
    {
        return $this->prefix;
    }


    /**
     * Setter for $this->prefix
     *
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = (string)$prefix;
    }


    /**
     * Alias of getCollection()
     * This is the same name as in php's MongoDB class
     *
     * @param string $collName
     *
     * @return object
     */
    public function selectCollection($collName)
    {
        return $this->getCollection($collName);
    }


    /**
     * Magic getter to simplify selecting collection
     * Same as getCollection() but simpler code
     * $this->Registry->Mongo->USERS
     * the same as $this->Registry->Mongo->getCollection('USERS')
     *
     * @param string $name
     *
     * @return object of type MongoCollection
     */
    public function __get($name)
    {
        return $this->getCollection($name);
    }


    /**
     * Getter for $this->conn
     *
     * @return object Mongo
     */
    public function getMongo()
    {
        return $this->conn;
    }

}
