<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\API;

use App\Entity\Customer;
use App\Entity\User;
use App\Tests\Mocks\CustomerTestMetaFieldSubscriberMock;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group integration
 */
class CustomerControllerTest extends APIControllerBaseTest
{
    public function testIsSecure()
    {
        $this->assertUrlIsSecured('/api/customers');
    }

    public function testGetCollection()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $this->assertAccessIsGranted($client, '/api/customers');
        $result = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals(1, count($result));
        $this->assertStructure($result[0], false);
    }

    public function testGetCollectionWithQuery()
    {
        $query = ['order' => 'ASC', 'orderBy' => 'name', 'visible' => 3];
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $this->assertAccessIsGranted($client, '/api/customers', 'GET', $query);
        $result = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals(1, count($result));
        $this->assertStructure($result[0], false);
    }

    public function testGetEntity()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $this->assertAccessIsGranted($client, '/api/customers/1');
        $result = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($result);
        $this->assertStructure($result, true);
    }

    public function testNotFound()
    {
        $this->assertEntityNotFound(User::ROLE_USER, '/api/customers/2');
    }

    public function testPostAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'visible' => true,
            'country' => 'DE',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
        ];
        $this->request($client, '/api/customers', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        $this->assertStructure($result);
        $this->assertNotEmpty($result['id']);
    }

    public function testPostActionWithInvalidUser()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $data = [
            'name' => 'foo',
            'visible' => true,
            'country' => 'DE',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
        ];
        $this->request($client, '/api/customers', 'POST', [], json_encode($data));
        $response = $client->getResponse();
        $this->assertFalse($response->isSuccessful());
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('User cannot create customers', $json['message']);
    }

    public function testPostActionWithInvalidData()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'visible' => true,
            'country' => 'XYZ',
            'currency' => '---',
            'timezone' => 'foo/bar',
            'unexpected' => 'field',
        ];
        $this->request($client, '/api/customers', 'POST', [], json_encode($data));
        $response = $client->getResponse();
        $this->assertApiCallValidationError($response, ['country', 'currency', 'timezone'], true);
    }

    public function testPatchAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'comment' => '',
            'visible' => true,
            'country' => 'DE',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
        ];
        $this->request($client, '/api/customers/1', 'PATCH', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        $this->assertStructure($result);
        $this->assertNotEmpty($result['id']);
    }

    public function testPatchActionWithInvalidUser()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);

        $data = [
            'name' => 'foo',
            'comment' => '',
            'visible' => true,
            'country' => 'DE',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
        ];
        $this->request($client, '/api/customers/1', 'PATCH', [], json_encode($data));
        $response = $client->getResponse();
        $this->assertFalse($response->isSuccessful());
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('User cannot update customer', $json['message']);
    }

    public function testPatchActionWithUnknownActivity()
    {
        $this->assertEntityNotFoundForPatch(User::ROLE_USER, '/api/customers/255', []);
    }

    public function testInvalidPatchAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'visible' => true,
            'country' => 'DE',
            'currency' => 'XXX',
            'timezone' => 'Europe/Berlin',
        ];
        $this->request($client, '/api/customers/1', 'PATCH', [], json_encode($data));

        $response = $client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertApiCallValidationError($response, ['currency']);
    }

    public function testMetaActionThrowsNotFound()
    {
        $this->assertEntityNotFoundForPatch(User::ROLE_ADMIN, '/api/customers/42/meta', []);
    }

    public function testMetaActionThrowsExceptionOnMissingName()
    {
        return $this->assertExceptionForPatchAction(User::ROLE_ADMIN, '/api/customers/1/meta', ['value' => 'X'], [
            'code' => 400,
            'message' => 'Parameter "name" of value "NULL" violated a constraint "This value should not be null."'
        ]);
    }

    public function testMetaActionThrowsExceptionOnMissingValue()
    {
        return $this->assertExceptionForPatchAction(User::ROLE_ADMIN, '/api/customers/1/meta', ['name' => 'X'], [
            'code' => 400,
            'message' => 'Parameter "value" of value "NULL" violated a constraint "This value should not be null."'
        ]);
    }

    public function testMetaActionThrowsExceptionOnMissingMetafield()
    {
        return $this->assertExceptionForPatchAction(User::ROLE_ADMIN, '/api/customers/1/meta', ['name' => 'X', 'value' => 'Y'], [
            'code' => 500,
            'message' => 'Unknown meta-field requested'
        ]);
    }

    public function testMetaAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $client->getContainer()->get('event_dispatcher')->addSubscriber(new CustomerTestMetaFieldSubscriberMock());

        $data = [
            'name' => 'metatestmock',
            'value' => 'another,testing,bar'
        ];
        $this->request($client, '/api/customers/1/meta', 'PATCH', [], json_encode($data));

        $this->assertTrue($client->getResponse()->isSuccessful());

        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        /** @var Customer $customer */
        $customer = $em->getRepository(Customer::class)->find(1);
        $this->assertEquals('another,testing,bar', $customer->getMetaField('metatestmock')->getValue());
    }

    protected function assertStructure(array $result, $full = true)
    {
        $expectedKeys = [
            'id', 'name', 'visible', 'hourlyRate', 'fixedRate', 'color', 'metaFields'
        ];

        if ($full) {
            $expectedKeys = array_merge($expectedKeys, [
                'homepage', 'number', 'comment', 'company', 'contact', 'address', 'country', 'currency',
                'phone', 'fax', 'mobile', 'email', 'timezone', 'budget', 'timeBudget'
            ]);
        }

        $actual = array_keys($result);
        sort($actual);
        sort($expectedKeys);

        $this->assertEquals($expectedKeys, $actual, 'Customer structure does not match');
    }
}
