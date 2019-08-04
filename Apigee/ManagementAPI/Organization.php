<?php
/**
 * @file
 * Abstracts the Organization object in the Management API and allows clients to
 * manipulate it.
 *
 * @author djohnson
 */
namespace Apigee\ManagementAPI;

use Apigee\Util\OrgConfig;

/**
 * Abstracts the Organization object in the Management API and allows clients to
 * manipulate it.
 *
 * @author djohnson
 */
class Organization extends Base
{

    /**
     * @var string
     * The internal name of the organization.
     */
    protected $name;

    /**
     * @var string
     * The dispaly name of the organization.
     */
    protected $displayName;

    /**
     * @var string[]
     * Environments available in the organization. By default 'test' and 'prod'
     * environments are available.
     */
    protected $environments;

    /**
     * @var string[]
     * A list of descriptors used internally by Apigee.
     */
    protected $properties;

    /**
     * @var string
     * Organization type. Currently 'trial' and 'paid' are valid.
     */
    protected $type;

    /**
     * @var int
     * Unix time when the organization was created.
     */
    protected $createdAt;

    /**
     * @var string
     * Username of the Apigee user who created the organization.
     */
    protected $createdBy;

    /**
     * @var int
     * Unix time when the organization was last modified.
     */
    protected $lastModifiedAt;

    /**
     * @var string
     * Username of the Apigee user who last modified the organization.
     */
    protected $lastModifiedBy;

    /**
     * Returns the internal name of the organization.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the display name of the organization.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * Returns the environments available in the organization. By default 'test'
     * and 'prod' environments are available.
     *
     * @return string[]
     */
    public function getEnvironments()
    {
        return $this->environments;
    }

    /**
     * Returns a list of descriptors used internally by Apigee.
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Returns a named descriptor used internally by Apigee.
     *
     * @param string $name
     * @return string|null
     */
    public function getProperty($name)
    {
        return array_key_exists($name, $this->properties) ? $this->properties[$name] : null;
    }

    /**
     * Returns the organization type. Currently 'trial' and 'paid' are valid.
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the Unix time when the organization was created.
     *
     * @return int
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Returns the username of the Apigee user who created the organization.
     *
     * @return string
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * Returns the Unix time when the organization was last modified.
     *
     * @return int
     */
    public function getLastModifiedAt()
    {
        return $this->lastModifiedAt;
    }

    /**
     * Returns the username of the Apigee user who last modified the organization.
     *
     * @return string
     */
    public function getLastModifiedBy()
    {
        return $this->lastModifiedBy;
    }

    /**
     * Initializes default values of all member variables.
     *
     * @param OrgConfig $config
     */
    public function __construct(OrgConfig $config)
    {
        $this->init($config, '/organizations');
        $this->name = $config->orgName;
    }

    /**
     * Loads an organization.
     *
     * @param string|null $orgName
     */
    public function load($orgName = null)
    {
        $orgName = $orgName ? : $this->name;
        $this->get(rawurlencode($orgName));
        $org = $this->responseObj;

        $this->name = $org['name'];
        $this->displayName = $org['displayName'];
        $this->environments = $org['environments'];
        $this->type = $org['type'];
        $this->createAt = $org['createdAt'];
        $this->createdBy = $org['createdBy'];
        $this->lastModifiedAt = $org['lastModifiedAt'];
        $this->lastModifiedBy = $org['lastModifiedBy'];
        $this->properties = array();

        if (array_key_exists('properties', $org) && array_key_exists('property', $org['properties'])) {
            foreach ($org['properties']['property'] as $prop) {
                if (array_key_exists('name', $prop) && array_key_exists('value', $prop)) {
                    $this->properties[$prop['name']] = $prop['value'];
                }
            }
        }
    }
}
