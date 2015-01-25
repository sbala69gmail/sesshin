<?php
namespace Sesshin\Tests;

use Sesshin\Session;
use Sesshin\Event;

class SessionTest extends TestCase
{
    /**
     * @var Sesshin\Session
     */
    private function setUpDefaultSession($session = null)
    {
        if (is_null($session)) {
            $session = new Session();
        }

        $id_handler = $this->getMock('\Sesshin\Id\Handler', array('generateId', 'getId', 'setId', 'issetId', 'unsetId'));
        $session->setIdHandler($id_handler);

        $storage = $this->getMock('\Sesshin\Storage\StorageInterface', array('store', 'fetch', 'delete'));
        $session->setStorage($storage);

        $eventEmitter = $this->getMock('\League\Event\Emitter', array('emit', 'addListener'));
        $session->setEventEmitter($eventEmitter);

        return $session;
    }

    /**
     * @covers Sesshin\Session::setValue
     */
    public function testValueIsSetToDefaultNamespaceByDefault()
    {
        $session = $this->setUpDefaultSession();
        $ref_prop = $this->setPropertyAccessible($session, 'values');

        $session->setValue('name', 'value');

        $values = $ref_prop->getValue($session);
        $this->assertEquals('value', $values[Session::DEFAULT_NAMESPACE]['name']);
    }

    /**
     * @covers Sesshin\Session::setValue
     */
    public function testCanSetValueToCustomNamespace()
    {
        $session = $this->setUpDefaultSession();
        $ref_prop = $this->setPropertyAccessible($session, 'values');

        $session->setValue('name', 'value', 'namespace');

        $values = $ref_prop->getValue($session);
        $this->assertEquals('value', $values['namespace']['name']);
    }

    /**
     * @covers Sesshin\Session::getValue
     * @depends testValueIsSetToDefaultNamespaceByDefault
     */
    public function testCanGetValue()
    {
        $session = $this->setUpDefaultSession();
        $session->setValue('name', 'value');
        $this->assertSame('value', $session->getValue('name'));
    }

    /**
     * @covers Sesshin\Session::getValue
     * @depends testValueIsSetToDefaultNamespaceByDefault
     */
    public function testCanGetValueMethodReturnsNullIfNoValueForGivenName()
    {
        $session = $this->setUpDefaultSession();
        $this->assertNull($session->getValue('name'));
    }

    /**
     * @covers Sesshin\Session::issetValue
     * @depends testValueIsSetToDefaultNamespaceByDefault
     */
    public function testCanCheckIfValueIsSet()
    {
        $session = $this->setUpDefaultSession();
        $session->setValue('name', 'value');
        $this->assertTrue($session->issetValue('name'));
    }

    /**
     * @covers Sesshin\Session::unsetValue
     * @depends testValueIsSetToDefaultNamespaceByDefault
     */
    public function testCanUnsetValues()
    {
        $session = $this->setUpDefaultSession();
        $session->setValue('name', 'value');
        $session->unsetValue('name');
        $this->assertNull($session->getValue('name'));
    }

    /**
     * @covers Sesshin\Session::getUnsetValue
     */
    public function testCanGetAndUnsetValue()
    {
        $session = $this->setUpDefaultSession();
        $session->setValue('name', 'value');
        $value = $session->getUnsetValue('name');
        $this->assertEquals('value', $value);
        $this->assertNull($session->getValue('name'));
    }

    /**
     * @covers Sesshin\Session::getValues
     */
    public function testCanGetAllValuesForNamespace()
    {
        $session = $this->setUpDefaultSession();
        $session->setValue('name1', 'value1');
        $session->setValue('name2', 'value2');
        $this->assertEquals(array('name1' => 'value1', 'name2' => 'value2'), $session->getValues());
    }

    /**
     * @covers Sesshin\Session::unsetValues
     */
    public function testCanUnsetAllValuesForNamespace()
    {
        $session = $this->setUpDefaultSession();
        $session->setValue('name1', 'value1');
        $session->setValue('name2', 'value2');
        $session->unsetValues();
        $this->assertNull($session->getValue('name1'));
        $this->assertNull($session->getValue('name2'));
        $this->assertEmpty($session->getValues());
    }

    /**
     * @covers Sesshin\Session::getRequestsCounter
     */
    public function testCanGetRequestsCounter()
    {
        $session = $this->setUpDefaultSession();
        $value = 37;
        $this->setPropertyValue($session, 'requests_counter', $value);
        $this->assertEquals($value, $session->getRequestsCounter());
    }

    /**
     * @covers Sesshin\Session::create
     */
    public function testCreateMethodGeneratesId()
    {
        $session = $this->setUpDefaultSession();
        $session->getIdHandler()->expects($this->once())->method('generateId');
        $session->create();
    }

    /**
     * @covers Sesshin\Session::create
     */
    public function testCreateMethodUnsetsAllValues()
    {
        $session = $this->setUpDefaultSession();
        $ref_prop_values = $this->setPropertyValue($session, 'values', array(1, 2, 3, 4));
        $session->create();
        $this->assertEmpty($ref_prop_values->getValue($session));
    }

    /**
     * @covers Sesshin\Session::create
     */
    public function testCreateMethodResetsFirstTrace()
    {
        $session = $this->setUpDefaultSession();
        $first_trace = $session->getFirstTrace();
        $session->create();
        $this->assertNotEquals($first_trace, $session->getFirstTrace());
    }

    /**
     * @covers Sesshin\Session::create
     */
    public function testCreateMethodResetsLastTrace()
    {
        $session = $this->setUpDefaultSession();
        $last_trace = $session->getLastTrace();
        $session->create();
        $this->assertNotEquals($last_trace, $session->getLastTrace());
    }

    /**
     * @covers Sesshin\Session::create
     */
    public function testCreateMethodResetsRequestsCounter()
    {
        $session = $this->setUpDefaultSession();
        $session->create();
        $this->assertEquals(1, $session->getRequestsCounter());
    }

    /**
     * @covers Sesshin\Session::create
     */
    public function testCreateMethodResetsIdRegenerationTrace()
    {
        $session = $this->setUpDefaultSession();
        $regeneration_trace = $session->getRegenerationTrace();
        $session->create();
        $this->assertNotEquals($regeneration_trace, $session->getRegenerationTrace());

        $value = 1;
        $session = $this->setUpDefaultSession();
        $this->setPropertyValue($session, 'regeneration_trace', $value);
        $session->create();
        $this->assertNotEquals($value, $session->getRegenerationTrace());

        $this->assertGreaterThanOrEqual(time() - 1, $session->getRegenerationTrace());
    }

    /**
     * @covers Sesshin\Session::create
     */
    public function testCreateMethodGeneratesFingerprint()
    {
        $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session', array('generateFingerprint')));
        $session->expects($this->once())->method('generateFingerprint');
        $session->create();
    }

    /**
     * @covers Sesshin\Session::create
     */
    public function testCreateMethodOpensSession()
    {
        $session = $this->setUpDefaultSession();
        $session->create();
        $this->assertEquals(true, $session->isOpened());
    }

    /**
     * @covers Sesshin\Session::open
     */
    public function testOpenMethodWhenCalledWithTrueThenCreatesNewSessionIfSessionNotExistsAlready()
    {
        $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session', array('create')));
        $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(false));
        $session->expects($this->once())->method('create');

        $session->open(true);
    }

    /**
     * @covers Sesshin\Session::open
     */
    public function testOpenMethodWhenCalledWithTrueThenDoesNotCreateNewSessionIfSessionIdExistsAlready()
    {
        $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session', array('create')));
        $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(true));
        $session->expects($this->never())->method('create');

        $session->open(true);
    }

    /**
     * @covers Sesshin\Session::open
     */
    public function testOpenMethodWhenCalledWithFalseThenDoesNotCreateNewSession()
    {
        $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session', array('create')));
        $session->expects($this->never())->method('create');

        $session->open(false);
    }

    /**
     * @covers Sesshin\Session::open
     */
    public function testOpenMethodLoadsSessionDataIfSessionExists()
    {
        $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session', array('create', 'load')));
        $session->expects($this->any())->method('isOpened')->will($this->returnValue(false));
        $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(true));
        $session->expects($this->once())->method('load');

        $session->open();
    }

    /**
     * @covers Sesshin\Session::open
     */
    public function testOpenMethodDoesNotLoadSessionDataIfSessionNotExists()
    {
        $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session', array('create', 'load')));
        $session->expects($this->any())->method('isOpened')->will($this->returnValue(false));
        $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(false));
        $session->expects($this->never())->method('load');

        $session->open();
    }

    /**
     * @covers Sesshin\Session::open
     */
    public function testOpenMethodTriggersSessionNoDataOrExpiredEventIfNoDataPresentAfterLoad()
    {
        $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session', array('create', 'load', 'getFirstTrace')));
        $session->expects($this->any())->method('isOpened')->will($this->returnValue(false));
        $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(true));
        $session->expects($this->once())->method('getFirstTrace')->will($this->returnValue(false));
        $session->getEventEmitter()->expects($this->once())->method('emit')->with($this->equalTo(new Event(Event::NO_DATA_OR_EXPIRED, $session)));

        $session->open();
    }

    /**
     * @covers Sesshin\Session::open
     */
    public function testOpenMethodTriggersSessionExpiredEventIfSessionExpired()
    {
        $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session', array('create', 'load', 'getFirstTrace', 'isExpired')));
        $session->expects($this->any())->method('isOpened')->will($this->returnValue(false));
        $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(true));
        $session->expects($this->once())->method('getFirstTrace')->will($this->returnValue(time()));
        $session->expects($this->once())->method('isExpired')->will($this->returnValue(true));
        $session->getEventEmitter()->expects($this->once())->method('emit')->with($this->equalTo(new Event(Event::EXPIRED, $session)));

        $session->open();
    }

    /**
     * Fingerpring is generated, so it can be compared with the one in session
     * metadata for session validity.
     *
     * @covers Sesshin\Session::open
     */
    public function testOpenMethodTriggersInvalidFingerprintEventIfLoadedFingerprintInvalid()
    {
        $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session', array('create', 'load', 'getFirstTrace', 'isExpired', 'getFingerprint', 'generateFingerprint')));
        $session->expects($this->any())->method('isOpened')->will($this->returnValue(false));
        $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(true));
        $session->expects($this->once())->method('getFirstTrace')->will($this->returnValue(time()));
        $session->expects($this->once())->method('isExpired')->will($this->returnValue(false));
        $session->expects($this->once())->method('getFingerprint')->will($this->returnValue('abc'));
        $session->expects($this->once())->method('generateFingerprint')->will($this->returnValue('def'));
        $session->getEventEmitter()->expects($this->once())->method('emit')->with($this->equalTo(new Event(Event::INVALID_FINGERPRINT, $session)));

        $session->open();
    }

    /**
     * @covers Sesshin\Session::open
     * @depends testCanGetRequestsCounter
     */
    public function testOpenMethodOpenSessionAndIncrementsRequestsCounter()
    {
        $session = $this->setUpDefaultSession($this->getMock('\Sesshin\Session', array('create', 'load', 'getFirstTrace', 'isExpired', 'getFingerprint', 'generateFingerprint')));
        $session->expects($this->any())->method('isOpened')->will($this->returnValue(false));
        $session->getIdHandler()->expects($this->any())->method('issetId')->will($this->returnValue(true));
        $session->expects($this->once())->method('getFirstTrace')->will($this->returnValue(time()));
        $session->expects($this->once())->method('isExpired')->will($this->returnValue(false));
        $session->expects($this->once())->method('getFingerprint')->will($this->returnValue('abc'));
        $session->expects($this->once())->method('generateFingerprint')->will($this->returnValue('abc'));

        $requests_counter = $session->getRequestsCounter();

        $session->open();

        $this->assertSame(true, $session->isOpened());
        $this->assertEquals($requests_counter + 1, $session->getRequestsCounter());
    }

    /**
     * @covers Sesshin\Session::isOpen
     * @covers Sesshin\Session::isOpened
     */
    public function testCanCheckIfSessionIsOpened()
    {
        $session = $this->setUpDefaultSession();
        $this->setPropertyValue($session, 'opened', true);
        $this->assertSame(true, $session->isOpen());
        $this->assertSame(true, $session->isOpened());
        $this->setPropertyValue($session, 'opened', false);
        $this->assertSame(false, $session->isOpen());
        $this->assertSame(false, $session->isOpened());
    }

    /**
     * @covers Sesshin\Session::setIdHandler
     * @covers Sesshin\Session::getIdHandler
     */
    public function testCanSetGetIdHandler()
    {
        $session = new Session();
        $id_handler = new \Sesshin\Id\Handler();
        $session->setIdHandler($id_handler);
        $this->assertSame($id_handler, $session->getIdHandler());
    }

    /**
     * @covers Sesshin\Session::getIdHandler
     */
    public function testUsesDefaultIdHandlerIfNotSet()
    {
        $session = new Session();
        $this->assertEquals('Sesshin\Id\Handler', get_class($session->getIdHandler()));
    }

    /**
     * @covers Sesshin\Session::shouldRegenerateId
     */
    public function testSessionIdShouldBeRegeneratedIfIdRequestsLimitReached()
    {
        $session = new Session();
        $this->setPropertyValue($session, 'requests_counter', 5);
        $this->setPropertyValue($session, 'id_requests_limit', 5);
        $this->assertSame(true, $this->invokeMethod($session, 'shouldRegenerateId'));
    }

    /**
     * @covers Sesshin\Session::shouldRegenerateId
     */
    public function testSessionIdShouldBeRegeneratedIfIdTtlLimitReached()
    {
        $session = new Session();
        $this->setPropertyValue($session, 'id_ttl', 60);
        $this->setPropertyValue($session, 'regeneration_trace', time() - 90);
        $this->assertSame(true, $this->invokeMethod($session, 'shouldRegenerateId'));
    }

    /**
     * @covers Sesshin\Session::shouldRegenerateId
     */
    public function testSessionIdShouldNotBeRegeneratedIfLimitsNotReached()
    {
        $session = new Session();
        $this->setPropertyValue($session, 'requests_counter', 5);
        $this->setPropertyValue($session, 'id_requests_limit', 6);
        $this->setPropertyValue($session, 'id_ttl', 60);
        $this->setPropertyValue($session, 'regeneration_trace', time() - 30);
        $this->assertSame(false, $this->invokeMethod($session, 'shouldRegenerateId'));
    }

    /**
     * @covers Sesshin\Session::shouldRegenerateId
     */
    public function testSessionIdShouldNotBeRegeneratedIfLimitsNotSet()
    {
        $session = new Session();
        $this->assertSame(false, $this->invokeMethod($session, 'shouldRegenerateId'));
    }

    /**
     * @covers Sesshin\Session::setStorage
     * @covers Sesshin\Session::getStorage
     */
    public function testCanSetGetStorage()
    {
        $session = new Session();
        $storage = new \Sesshin\Storage\Files();
        $session->setStorage($storage);
        $this->assertSame($storage, $session->getStorage());
    }

    /**
     * @covers Sesshin\Session::getStorage
     */
    public function testUsesFilesStorageIfNotSet()
    {
        $session = new Session();
        $this->assertEquals('Sesshin\Storage\Files', get_class($session->getStorage()));
    }

    /**
     * @covers Sesshin\Session::setEventEmitter
     * @covers Sesshin\Session::getEventEmitter
     */
    public function testCanSetGetEventEmitter()
    {
        $session = new Session();
        $eventEmitter = new \League\Event\Emitter();
        $session->setEventEmitter($eventEmitter);
        $this->assertSame($eventEmitter, $session->getEventEmitter());
    }

    /**
     * @covers Sesshin\Session::getEventEmitter
     */
    public function testUsesDefaultEventEmitterIfNotSet()
    {
        $session = new Session();
        $this->assertEquals('League\Event\Emitter', get_class($session->getEventEmitter()));
    }

    /**
     * @covers Sesshin\Session::offsetSet
     * @covers Sesshin\Session::offsetGet
     * @covers Sesshin\Session::offsetExists
     * @covers Sesshin\Session::offsetUnset
     */
    public function testImplementsArrayAccessForSessionValues()
    {
        $session = $this->getMock('\Sesshin\Session', array('setValue'));
        $session->expects($this->once())->method('setValue')->with($this->equalTo('key'), $this->equalTo('value'));
        $session['key'] = 'value';

        $session = $this->getMock('\Sesshin\Session', array('getValue'));
        $session->expects($this->once())->method('getValue')->with($this->equalTo('key'));
        $session['key'];

        $session = $this->getMock('\Sesshin\Session', array('issetValue'));
        $session->expects($this->once())->method('issetValue')->with($this->equalTo('key'));
        isset($session['key']);

        $session = $this->getMock('\Sesshin\Session', array('unsetValue'));
        $session->expects($this->once())->method('unsetValue')->with($this->equalTo('key'));
        unset($session['key']);
    }

    /**
     * @covers Sesshin\Session::load
     * @depends testOpenMethodLoadsSessionDataIfSessionExists
     */
    public function testLoadMethodFetchesDataFromStorage()
    {
        $session = $this->setUpDefaultSession();
        $session->getStorage()->expects($this->any())->method('fetch')->with($this->equalTo($session->getId()));
        $this->invokeMethod($session, 'load');
    }

    /**
     * @covers Sesshin\Session::load
     */
    public function testLoadMethodReturnsFalseIfNoDataInStorage()
    {
        $session = $this->setUpDefaultSession();
        $session->getStorage()->expects($this->any())->method('fetch')->will($this->returnValue(false));
        $this->assertFalse($this->invokeMethod($session, 'load'));
    }
}
