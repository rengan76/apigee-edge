<?php

namespace Apigee\ManagementAPI;

use Apigee\Exceptions\ResponseException;
use Apigee\Exceptions\ParameterException;
use Apigee\Util\OrgConfig;

/**
 * Abstracts the CompanyInviteRequest object from the Management API and allows clients to manipulate it.
 *
 * @author Sudheesh
 */
class CompanyInviteRequest extends Base
{

    /**
     * The Company Id of the company associated with the request.
     * @var string
     */
    private $companyId;

    /**
     * The request created date.
     * @var string
     */
    private $created_at;

    /**
     * The Developer Id of the developer associated with the request.
     * @var string
     */
    private $developerId;

    /**
     * The unique Id of the request.
     * @var string
     */
    private $id;

    /**
     * The last modfied time.
     * @var int
     */
    private $lastmodified_at;

    /**
     * The org id
     * @var string
     */
    private $orgId;

    /**
     * The entity that created the request.
     * @var string
     */
    private $requestor;

    /**
     * The current state of the request.
     * @var string
     */
    private $state;

    /**
     * The type of the request.
     * @var string
     */
    private $type;

    /**
     * The last updated date for the request
     * @var string
     */
    private $updated;

    /**
     * The email id of the source developer.
     * @var string
     */
    private $sourceDeveloperEmail;

    /**
     * Gets the id of the request object.
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the id of the request object.
     *
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Gets the Company Id.
     *
     * @return string
     */
    public function getCompanyId()
    {
        return $this->companyId;
    }

    /**
     * Sets the Company Id.
     *
     * @param string $companyId
     */
    public function setCompanyId($companyId)
    {
        $this->companyId = $companyId;
    }

    /**
     * Gets the Developer Id.
     *
     * @return string
     */
    public function getDeveloperId()
    {
        return $this->developerId;
    }

    /**
     * Sets the Developer Id.
     *
     * @param string $developerId
     */
    public function setDeveloperId($developerId)
    {
        $this->developerId = $developerId;
    }

    /**
     * Gets the created time.
     *
     * @return int
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Gets the modified time.
     *
     * @return int
     */
    public function getLastModifedAt()
    {
        return $this->lastmodified_at;
    }

    /**
     * Gets the orgId.
     *
     * @return string
     */
    public function getOrgId()
    {
        return $this->orgId;
    }

    /**
     * Gets the requestor.
     *
     * @return string
     */
    public function getRequestor()
    {
        return $this->requestor;
    }

    /**
     * Sets the requestor.
     *
     * @param string $requestor
     */
    public function setRequestor($requestor)
    {
        $this->requestor = $requestor;
    }

    /**
     * Gets the state of the request.
     *
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Sets the state of the request.
     *
     * @param string $state
     */
    public function setSate($state)
    {
        $this->state = $state;
    }

    /**
     * Gets the type of the request.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets  the type of the request.
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Gets the source developer email.
     *
     * @return string
     */
    public function getSourceDeveloperEmail()
    {
        return $this->sourceDeveloperEmail;
    }

    /**
     * Sets the source developer email.
     *
     * @param string $sourceDeveloperEmail
     */
    public function setSourceDeveloperEmail($sourceDeveloperEmail)
    {
        $this->sourceDeveloperEmail = $sourceDeveloperEmail;
    }

    /**
     * Initializes default values of all the member variables
     * @param \Apigee\Util\OrgConfig $config
     */
    public function __construct(OrgConfig $config)
    {
        $this->init($config, '/o/' . rawurlencode($config->orgName) . '/requests');
        $this->blankValues();
    }

    /**
     * Set all memeber variables to the default values.
     */
    public function blankValues()
    {
        $this->companyId = '';
        $this->created_at = '';
        $this->developerId = '';
        $this->id = '';
        $this->lastmodified_at = '';
        $this->orgId = '';
        $this->requestor = '';
        $this->state = '';
        $this->type = '';
    }

    /**
     * Get all the requests for a company.
     * @param string $companyId
     * @param string|null $state
     * @return CompanyInviteRequest[]
     */
    public function getAllRequestsForCompany($companyId, $state = null)
    {
        $query = '?company_id=' . $companyId;
        if (!empty($state)) {
            $query .= '&state=' . $state;
        }
        $this->get($query);
        $config = $this->config;
        return self::loadRequestArray($this->responseObj, $config);
    }

    /**
     * Get all requests for a developer.
     * @param string $developerId
     * @param string|null $state
     * @return CompanyInviteRequest[]
     */
    public function getAllRequestsForDeveloper($developerId, $state = null)
    {
        $query = '?dev_id=' . $developerId;
        if (!empty($state)) {
            $query .= '&state=' . $state;
        }

        $this->get($query);
        $config = $this->config;
        return self::loadRequestArray($this->responseObj, $config);
    }

    /**
     * Get all requests for this org.
     *
     * @return CompanyInviteRequest[]
     */
    public function getAllRequestsForOrg()
    {
        $this->get();
        $config = $this->config;
        return self::loadRequestArray($this->responseObj, $config);
    }

    /**
     * Parses an Edge reponse and return an array of InviteRequest.
     * @param array $reponseobj
     * @param OrgConfig $config
     * @return CompanyInviteRequest[]
     */
    private static function loadRequestArray($reponseobj, OrgConfig $config)
    {
        $requests = array();
        foreach ($reponseobj as $response) {
            $invite_request = new CompanyInviteRequest($config);
            self::loadFromResponse($invite_request, $response);
            $requests[] = $invite_request;
        }
        return $requests;
    }

    /**
     * Parses response from Edge and populates a CompanyInviteRequest object.
     * @param CompanyInviteRequest $company_invite_request
     * @param array $response
     */
    private static function loadFromResponse(CompanyInviteRequest &$company_invite_request, array $response)
    {
        foreach ($response as $key => $value) {
            if (property_exists($company_invite_request, $key)) {
                $company_invite_request->$key = $value;
            }
        }
    }

    public function load($id)
    {
        //TODO: Add logic to get individual requests using the request id once we have the API ready.
    }

    /**
     * Saves an invite request object to the Edge server.
     *
     * If $forceUpdate is true then a PUT call is made to update an existing
     * request, otherwise a POST call is made to create a new request.
     *
     * @param boolean $forceUpdate
     */
    public function save($forceUpdate = false)
    {
        if ($forceUpdate === null) {
            try {
                $this->save(true);
            } catch (ResponseException $e) {
                if ($e->getCode() == 404) {
                    $this->save(false);
                } else {
                    throw $e;
                }
            }
            return;
        }

        $payload = array(
            'companyId' => $this->companyId,
            'developerId' => $this->developerId,
        );
        if (!empty($this->requestor)) {
            $payload['requestor'] = $this->requestor;
        }
        if (!empty($this->type)) {
            $payload['type'] = $this->type;
        }
        if (!empty($this->state)) {
            $payload['state'] = $this->state;
        }

        $url = null;
        if ($forceUpdate || $this->created_at) {
            $url = rawurldecode($this->id);
        }

        $headers = array('source' => $this->sourceDeveloperEmail);
        if ($forceUpdate) {
            $this->put($url, $payload, 'application/json; charset=utf-8', 'application/json; charset=utf-8', $headers);
        } else {
            $this->post($url, $payload, 'application/json; charset=utf-8', 'application/json; charset=utf-8', $headers);
        }
        self::loadFromResponse($this, $this->responseObj);
    }

    /**
     * Deletes a request object from the Edge.
     * @param string|null $id
     * @throws ParameterException
     */
    public function delete($id = null)
    {
        $id = $id? : $this->id;
        if (empty($id)) {
            throw new ParameterException('No requestId given.');
        }

        $headers = array('source' => $this->sourceDeveloperEmail);
        $this->httpDelete(rawurlencode($id), 'application/json; charset=utf-8', $headers);
        if ($id == $this->id) {
            $this->blankValues();
        }
    }

    /**
     * Turns this object's properties into an array for external use.
     *
     * @return array
     */
    public function toArray()
    {
        $properties = array_keys(get_object_vars($this));
        $excluded_properties = array_keys(get_class_vars(get_parent_class($this)));
        $output = array();
        foreach ($properties as $property) {
            if (!in_array($property, $excluded_properties)) {
                $output[$property] = $this->$property;
            }
        }
        $output['debugData'] = $this->getDebugData();
        return $output;
    }
}
