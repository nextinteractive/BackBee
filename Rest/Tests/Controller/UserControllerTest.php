<?php

/*
 * Copyright (c) 2011-2013 Lp digital system
 * 
 * This file is part of BackBuilder5.
 *
 * BackBuilder5 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * BackBuilder5 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with BackBuilder5. If not, see <http://www.gnu.org/licenses/>.
 */

namespace BackBuilder\Rest\Tests\Controller;

use Symfony\Component\HttpFoundation\Request;

use BackBuilder\Rest\Controller\UserController;
use BackBuilder\Tests\TestCase;

use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity,
    Symfony\Component\Security\Acl\Domain\ObjectIdentity;

use BackBuilder\Security\Acl\Permission\MaskBuilder;

use BackBuilder\Security\Token\UsernamePasswordToken,
    BackBuilder\Security\User,
    BackBuilder\Security\Group;


use BackBuilder\ApiClient\Auth\PrivateKeyAuth;

/**
 * Test for UserController class
 *
 * @category    BackBuilder
 * @package     BackBuilder\Security
 * @copyright   Lp digital system
 * @author      k.golovin
 * 
 * @coversDefaultClass \BackBuilder\Rest\Controller\UserController
 */
class UserControllerTest extends TestCase
{
    
    protected $user;
    
    protected function setUp()
    {
        $this->initAutoload();
        $bbapp = $this->getBBApp();
        $this->initDb($bbapp);
        $this->initAcl();
        $this->getBBApp()->setIsStarted(true);
        
        // save user
        $group = new Group();
        $group->setName('groupName');
        $group->setIdentifier('GROUP_ID');
        $bbapp->getEntityManager()->persist($group);
        
        // valid user
        $this->user = new User();
        $this->user->addGroup($group);
        $this->user->setLogin('user123');
        $this->user->setPassword('password123');
        $this->user->setActivated(true);
        $bbapp->getEntityManager()->persist($this->user);
        
        // inactive user
        $user = new User();
        $user->addGroup($group);
        $user->setLogin('user123inactive');
        $user->setPassword('password123');
        $user->setActivated(false);
        $bbapp->getEntityManager()->persist($user);
        
        $bbapp->getEntityManager()->flush();
        
        // login user
        $this->getSecurityContext()->setToken(new UsernamePasswordToken($this->user, []));
        
         // set up permissions
        $aclManager = $this->getBBApp()->getContainer()->get('security.acl_manager');
        $aclManager->insertOrUpdateClassAce(new ObjectIdentity('class', get_class($this->user)), UserSecurityIdentity::fromAccount($this->user), MaskBuilder::MASK_IDDQD);
    }
    
    protected function getController()
    {
        $controller = new UserController();
        $controller->setContainer($this->getBBApp()->getContainer());
        
        return $controller;
    }

    
    /**
     * @covers ::getAction
     */
    public function testGetAction()
    {
        // set up permissions
        $aclManager = $this->getBBApp()->getContainer()->get('security.acl_manager');
        $aclManager->insertOrUpdateObjectAce(ObjectIdentity::fromDomainObject($this->user), UserSecurityIdentity::fromAccount($this->user), MaskBuilder::MASK_IDDQD);
        
        
        $controller = $this->getController();
        $response = $controller->getAction($this->user->getId());
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $content = json_decode($response->getContent(), true);
        $this->assertInternalType('array', $content);
        
        $this->assertEquals($this->user->getId(), $content['id']);
    }
    
    /**
     * @covers ::getAction
     */
    public function testGetAction_invalidUser()
    {
        $controller = $this->getController();
        
        $response = $controller->getAction(13807548404);
        
        $this->assertEquals(404, $response->getStatusCode());
    }
    
    /**
     * @covers ::deleteAction
     */
    public function testDeleteAction()
    {
        // create user
        $user = new User();
        $user->setLogin('usernameToDelete')
                ->setPassword('password123')
                ->setActivated(true);
        
        $this->getBBApp()->getEntityManager()->persist($user);
        $this->getBBApp()->getEntityManager()->flush();
        $userId = $user->getId();
        
        // set up permissions
        $aclManager = $this->getBBApp()->getContainer()->get('security.acl_manager');
        $aclManager->insertOrUpdateObjectAce(ObjectIdentity::fromDomainObject($user), UserSecurityIdentity::fromAccount($this->user), MaskBuilder::MASK_DELETE);
        
        $this->assertInstanceOf('BackBuilder\Security\User', 
                $this->getBBApp()->getEntityManager()->getRepository('BackBuilder\Security\User')->find($userId));
        
        $controller = $this->getController();
        
        $response = $controller->deleteAction($user->getId());
        
        $this->assertEquals(204, $response->getStatusCode());
        
        $userAfterDelete = $this->getBBApp()->getEntityManager()->getRepository('BackBuilder\Security\User')->find($userId);
        $this->assertTrue(is_null($userAfterDelete));
    }
    
    /**
     * @covers ::deleteAction
     */
    public function testDeleteAction_invalidUser()
    {
        $controller = $this->getController();
        
        $response = $controller->deleteAction(13807548404);
        $this->assertEquals(404, $response->getStatusCode());
    }
    
    /**
     * @covers ::putAction
     */
    public function testPutAction()
    {
        // create user
        $user = new User();
        $user->setLogin('usernameToUpdate')
                ->setPassword('password123')
                ->setApiKeyEnabled(false)
                ->setApiKeyPrivate('PRIVATE_KEY')
                ->setApiKeyPublic('PUBLIC_KEY')
                ->setFirstname('FirstName')
                ->setLastname('LastName')
                ->setActivated(true);
        
        $this->getBBApp()->getEntityManager()->persist($user);
        $this->getBBApp()->getEntityManager()->flush();
        $userId = $user->getId();

        $controller = $this->getController();
        
        $data = array(
            'login' => 'username_updated',
            'api_key_enabled' => true,
            'api_key_public' => 'updated_api_key_public',
            'api_key_private' => 'updated_api_key_private',
            'firstname' => 'updated_first_name',
            'lastname' => 'updated_last_name',
            'activated' => false,
        );
        
        $response = $controller->putAction($user->getId(), new Request(array(), $data));
        
        $this->assertEquals(204, $response->getStatusCode());
        
        $userUpdated = $this->getBBApp()->getEntityManager()->getRepository('BackBuilder\Security\User')->find($userId);
        /* @var $userUpdated User */
        
        $this->assertEquals($data['login'], $userUpdated->getLogin());
        $this->assertEquals($data['api_key_enabled'], $userUpdated->getApiKeyEnabled());
        $this->assertEquals($data['api_key_public'], $userUpdated->getApiKeyPublic());
        $this->assertEquals($data['api_key_private'], $userUpdated->getApiKeyPrivate());
        $this->assertEquals($data['firstname'], $userUpdated->getFirstname());
        $this->assertEquals($data['lastname'], $userUpdated->getLastname());
        
        return $userId;
    }
    
    
    /**
     * @covers ::putAction
     */
    public function test_putAction_userDoesntExist()
    {
        $controller = $this->getController();
        
        $response = $controller->putAction('12024582905729', new Request());

        $this->assertEquals(404, $response->getStatusCode());
    }
    
    /**
     * @covers ::putAction
     */
    public function testPutAction_empty_required_fields()
    {
        // create user
        $user = new User();
        $user->setLogin('usernameToUpdate')
                ->setPassword('password123')
                ->setApiKeyEnabled(false)
                ->setApiKeyPrivate('PRIVATE_KEY')
                ->setApiKeyPublic('PUBLIC_KEY')
                ->setFirstname('FirstName')
                ->setLastname('LastName')
                ->setActivated(true);
        
        $this->getBBApp()->getEntityManager()->persist($user);
        $this->getBBApp()->getEntityManager()->flush();
        $userId = $user->getId();
        
        $controller = $this->getController();
        
        $response = $this->getBBApp()->getController()->handle(new Request(array(), array(
            'firstname' => '',
            'lastname' => '',
            'login' => '',
        ), array(
            'id' => $userId,
            '_action' => 'putAction',
            '_controller' => 'BackBuilder\Rest\Controller\UserController'
        ), array(), array(), array('REQUEST_URI' => '/rest/1/test/') ));
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $res = json_decode($response->getContent(), true);

        $this->assertContains('First Name is required', $res['errors']['firstname']);
        $this->assertContains('Last Name is required', $res['errors']['lastname']);
        $this->assertContains('Login is required', $res['errors']['login']);
    }
    
    /**
     * @covers ::postAction
     */
    public function test_postAction()
    {
        // set up permissions
        $aclManager = $this->getBBApp()->getContainer()->get('security.acl_manager');
        $aclManager->insertOrUpdateClassAce(new ObjectIdentity('class', get_class($this->user)), UserSecurityIdentity::fromAccount($this->user), MaskBuilder::MASK_CREATE);
        
        $controller = $this->getController();
        
        $data = array(
            'login' => 'username',
            'api_key_enabled' => true,
            'api_key_public' => 'api_key_public',
            'api_key_private' => 'api_key_private',
            'firstname' => 'first_name',
            'lastname' => 'last_name',
            'activated' => false,
            'password' => 'password',
        );
        
        $response = $controller->postAction(new Request([], $data));
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        
        $res = json_decode($response->getContent(), true);
        
        $this->assertInternalType('array', $res);
        
        $this->assertEquals($data['login'], $res['login']);
        $this->assertEquals($data['api_key_enabled'], $res['api_key_enabled']);
        $this->assertEquals($data['api_key_public'], $res['api_key_public']);
        $this->assertEquals($data['api_key_private'], $res['api_key_private']);
        $this->assertEquals($data['firstname'], $res['firstname']);
        $this->assertEquals($data['lastname'], $res['lastname']);
        
        
        $this->assertArrayHasKey('id', $res);
        
        $user = $this->getBBApp()->getEntityManager()->getRepository('BackBuilder\Security\User')->find($res['id']);
        $this->assertInstanceOf('BackBuilder\Security\User', $user);
    }
    
    /**
     * @covers ::postAction
     */
    public function testPostAction_missing_required_fields()
    {
        $response = $this->getBBApp()->getController()->handle(new Request(array(), array(), array(
            '_action' => 'postAction',
            '_controller' => 'BackBuilder\Rest\Controller\UserController'
        ), array(), array(), array('REQUEST_URI' => '/rest/1/test/') ));
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $res = json_decode($response->getContent(), true);

        $this->assertContains('Password not provided', $res['errors']['password']);
        $this->assertContains('First Name is required', $res['errors']['firstname']);
        $this->assertContains('Last Name is required', $res['errors']['lastname']);
        $this->assertContains('Login is required', $res['errors']['login']);
    }
    
    /**
     * @covers ::postAction
     * @expectedException \Symfony\Component\HttpKernel\Exception\ConflictHttpException
     * @expectedExceptionMessage User with that login already exists: usernameDuplicate
     */
    public function test_postAction_duplicate_login()
    {
        // set up permissions
        $aclManager = $this->getBBApp()->getContainer()->get('security.acl_manager');
        $aclManager->insertOrUpdateClassAce(new ObjectIdentity('class', get_class($this->user)), UserSecurityIdentity::fromAccount($this->user), MaskBuilder::MASK_CREATE);
        $controller = $this->getController();
        
        // create user
        $user = new User();
        $user->setLogin('usernameDuplicate')
            ->setPassword('password123')
            ->setApiKeyEnabled(false)
            ->setApiKeyPrivate('PRIVATE_KEY')
            ->setApiKeyPublic('PUBLIC_KEY')
            ->setFirstname('FirstName')
            ->setLastname('LastName')
            ->setActivated(true);
        $this->getBBApp()->getEntityManager()->persist($user);
        $this->getBBApp()->getEntityManager()->flush();
        
        $response = $controller->postAction(new Request([], [
            'login' => 'usernameDuplicate',
            'api_key_enabled' => true,
            'api_key_public' => 'api_key_public',
            'api_key_private' => 'api_key_private',
            'firstname' => 'first_name',
            'lastname' => 'last_name',
            'activated' => false,
            'password' => 'password',
        ]));
    }

    protected function tearDown()
    {
        $this->dropDb($this->getBBApp());
        $this->getBBApp()->stop();
    }
    
    /**
     * 
     * @param type $uri
     * @param array $data
     * @param type $contentType
     * @param array $headers
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected static function requestPost($uri, array $data = [], $contentType = 'application/json', $sign = false)
    {
        $request = new Request([], $data, [], [], [], ['REQUEST_URI' => $uri, 'CONTENT_TYPE' => $contentType, 'REQUEST_METHOD' => 'POST'] );
        
        if($sign) {
            self::signRequest($request);
        }
        
        return $request;
    }
    
    /**
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param BackBuilder\Security\User $user
     * @return self
     */
    protected static function signRequest(Request $request, BackBuilder\Security\User $user = null)
    {
        if(null === $user) {
            $user = $this->user;
        }
        
        $auth = new PrivateKeyAuth();
        $auth->setPrivateKey($user->getApiKeyPrivate());
        $auth->setPublicKey($user->getApiKeyPublic());
        $request->headers->add([
            PrivateKeyAuth::AUTH_PUBLIC_KEY_TOKEN => $user->getApiKeyPublic(),
            PrivateKeyAuth::AUTH_SIGNATURE_TOKEN => $auth->getRequestSignature($request->getMethod(), $request->getRequestUri())
        ]);

        return self;
    }
}