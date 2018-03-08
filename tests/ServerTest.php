<?php

/**
 * JsonRpcXp
 *
 * Copyright (c) 2013-2014, Alexander Wühr <lx@boolshit.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * * Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in
 * the documentation and/or other materials provided with the
 * distribution.
 *
 * * Neither the name of Alexander Wühr nor the names of his
 * contributors may be used to endorse or promote products derived
 * from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package   Lx\JsonRpcXp
 * @author    Alexander Wühr <lx@boolshit.de>
 * @copyright 2014 Alexander Wühr <lx@boolshit.de>
 * @license   http://opensource.org/licenses/MIT The MIT License (MIT)
 * @link      https://github.com/l-x/JsonRpcXp
 */

namespace Lx\JsonRpcXp;

require_once __DIR__.'/../vendor/autoload.php';

/**
 * Class TestObject
 *
 * @package Lx\JsonRpcXp
 */
class TestObject {

	public function foo() {

	}

	public function bar() {

	}

	protected function baz() {

	}
}

/**
 * Class ServerProxy
 *
 * Proxy object for access to protected methods and members
 *
 * @package Lx\JsonRpcXp
 */
class ServerProxy extends Server {

	public function get($key) {
		return $this->$key;
	}

	public function set($key, $value) {
		$this->$key = $value;
	}

	public function call($method, $arguments = array()) {
		return call_user_func_array(array($this, $method), $arguments);
	}
}

/**
 * Class ServerTest
 *
 * @package Lx\JsonRpcXp
 */
class ServerTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var Server
	 */
	protected $obj;

	/**
	 * @var \stdClass
	 */
	protected $message;

	/**
	 * Testcase setup
	 */
	public function setUp() {

		$this->obj = new ServerProxy();
		$this->message = (object) array(
			'id' => 'id',
			'jsonrpc' => '2.0',
			'method' => 'foo',
			'params' => array('bar'),
		);
		$this->obj->set(
			'callbacks',
			array(
				$this->message->method => true
			)
		);
	}

	/**
	 * @param $exception
	 * @param $id
	 *
	 * @return \PHPUnit_Framework_MockObject_MockObject
	 */
	public function getValidateMessageFaultMock() {
		$mock = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('fault', 'getCallback'))
			->getMock();

		$mock->expects($this->once())->method('fault')->will(($this->returnArgument(0)));
		$mock->expects($this->any())->method('getCallback')->will($this->returnValue('callback'));

		return $mock;
	}

	public function getValidateMessageSuccessMock() {
		$mock = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('fault', 'getCallback'))
			->getMock();

		$mock->expects($this->never())->method('fault');
		$mock->expects($this->any())->method('getCallback')->will($this->returnValue('callback'));

		return $mock;
	}

	/**
	 * @test
	 * @testdox                  Server::registerException() throws an exception on non existing exception class
	 *
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Argument must be a valid exception class or array of valid exception classes
	 */
	public function registerExceptionThrowsExceptionOnNonExistingClass() {
		$this->obj->registerException('snafu');
	}

	/**
	 * @test
	 * @testdox Server::registerException() properly registers a single exception class
	 */
	public function registerExceptionAddsSingleClass() {
		$this->obj->registerException('Exception');
		$this->assertEquals(array('Exception'), $this->obj->get('registered_exceptions'));

		$this->obj->registerException('InvalidArgumentException');
		$this->assertEquals(
			array(
				'Exception', 'InvalidArgumentException'
			),
			$this->obj->get('registered_exceptions')
		);
	}

	/**
	 * @test
	 * @testdox Server::registerException() properly registers an array of exception classes
	 */
	public function registerExceptionAddsMultipleClasses() {
		$classes = array('Exception', 'InvalidArgumentException');

		$this->obj->registerException($classes);
		$this->assertEquals($classes, $this->obj->get('registered_exceptions'));
	}

	/**
	 * @test
	 * @testdox Server::registerException() eliminates double registered exceptions
	 */
	public function registerExceptionEleminatesDupes() {
		$this->obj->registerException('Exception');
		$this->obj->registerException('Exception');
		$this->assertEquals(array('Exception'), $this->obj->get('registered_exceptions'));

		$this->obj->registerException(array('Exception', 'Exception'));
		$this->assertEquals(array('Exception'), $this->obj->get('registered_exceptions'));
	}


	/**
	 * @test
	 * @testdox Server::isExceptionRegistered() returns proper value
	 */
	public function isExceptionRegistered() {
		$this->obj->set('registered_exceptions', array('InvalidArgumentException'));

		$this->assertTrue($this->obj->call('isExceptionRegistered', array(new \InvalidArgumentException())));
		$this->assertFalse($this->obj->call('isExceptionRegistered', array(new \Exception())));
	}

	/**
	 * @test
	 * @testdox Server::fault() returns proper structure
	 */
	public function fault() {
		$id = 'test';

		$fault = $this
			->getMockBuilder(__NAMESPACE__.'\Fault')
			->setMethods(array('toArray'))
			->getMock();

		$fault->expects($this->once())->method('toArray')->will($this->returnValue(array('fault')));

		$obj = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('getMessageStub'))
			->getMock();

		$obj->expects($this->once())->method('getMessageStub')->with($id)->will(
				$this->returnValue(array('stub'))
			);

		$expected = array(
			'stub', 'error' => array('fault'),
		);

		$this->assertEquals($expected, $obj->call('fault', array($fault, $id)));
	}

	/**
	 * @test
	 * @testdox Server::getRemoteProcedureName() returns the correct value
	 */
	public function getRemoteProcedureName() {
		$this->assertEquals('foo', $this->obj->call('getRemoteProcedureName', array('foo', '')));
		$this->assertEquals('bar.foo', $this->obj->call('getRemoteProcedureName', array('foo', 'bar')));
	}

	/**
	 * @test
	 * @testdox Server::registerObject() properly calls Server::registerFunction() for every object's method
	 */
	public function registerObject() {
		$object = new TestObject();
		$namespace = 'foo';
		$methods = get_class_methods($object);

		$obj = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('registerFunction'))
			->getMock();

		$obj->expects($this->exactly(count($methods)))->method('registerFunction')->will($this->returnSelf());

		$obj->registerObject($object, $namespace);
	}

	/**
	 * @test
	 * @testdox Server::registerFunction() properly wraps and registers callback
	 */
	public function registerFunction() {
		$name = 'bar';
		$namespace = 'foo';
		$callback = 'baz';
		$rp_name = 'rp_name';

		$expected = array($rp_name => $callback);

		$obj = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('wrapCallback', 'getRemoteProcedureName'))
			->getMock();

		$obj->expects($this->once())->method('wrapCallback')->with($callback)->will($this->returnArgument(0));
		$obj->expects($this->once())->method('getRemoteProcedureName')->with($name, $namespace)->will($this->returnValue($rp_name));

		$obj->registerFunction($name, $callback, $namespace);
		$this->assertEquals($expected, $obj->get('callbacks'));
	}

	/**
	 * @test
	 * @testdox Server::validateMessage() return true on valid message
	 */
	public function validateMessageReturnsTrueOnSuccess() {
		$obj = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('fault', 'getCallback'))
			->getMock();

		$obj->set(
			'callbacks',
			array(
				$this->message->method => true
			)
		);

		$obj->expects($this->never())->method('fault');
		$obj->expects($this->once())->method('getCallback')->with($this->message->method)->will($this->returnValue('callback'));

		$this->assertTrue($obj->call('validateMessage', array($this->message)));
	}

	public function assertValidateMessageFault($message, $fault_class) {
		$obj = $this->getValidateMessageFaultMock();

		$result = $obj->call('validateMessage', array($message));
		$this->assertInstanceOf(__NAMESPACE__.'\Fault\\'.$fault_class, $result);
	}

	/**
	 * @test
	 * @testdox Server::validateMessage() returns InvalidRequest fault on missing or wrong version string
	 */
	public function validateMessageReturnsFaultOnMissingOrWrongVersion() {
		$message = clone $this->message;
		$message->jsonrpc = '3.0';
		$this->assertValidateMessageFault($message, 'InvalidRequest');

		unset($message->jsonrpc);
		$this->assertValidateMessageFault($message, 'InvalidRequest');
	}

	/**
	 * @test
	 * @testdox Server::validateMessage() returns InvalidRequest fault on missing method parameter
	 */
	public function validateMessageReturnsFaultOnMissingMethodName() {
		$message = clone $this->message;
		unset($message->method);
		$this->assertValidateMessageFault($message, 'InvalidRequest');
	}

	/**
	 * @test
	 * @testdox Server::validateMessage() returns MethodNotFound fault on unknown method
	 */
	public function validateMessageReturnsFaultOnUnknownMethod() {
		$message = clone $this->message;
		$message->method = 'nonexisting';

		$obj = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('fault', 'getCallback'))
			->getMock();

		$obj->expects($this->once())->method('fault')->will(($this->returnArgument(0)));
		$obj->expects($this->once())->method('getCallback')->with('nonexisting')->will($this->returnValue(false));

		$result = $obj->call('validateMessage', array($message));
		$this->assertInstanceOf(__NAMESPACE__.'\Fault\MethodNotFound', $result);
	}

	/**
	 * @test
	 * @testdox Server::validateMessage() returns InvalidParams fault on invalid parameter type
	 */
	public function validateMessageReturnsFaultOnInvalidParams() {
		$message = clone $this->message;
		$message->params = 'some string';
		$this->assertValidateMessageFault($message, 'InvalidParams');
	}

	/**
	 * @test
	 * @testdox Server::validateMessage() sets message's id to null when missing
	 */
	public function validateMessageSetsIdWhenMissing() {
		$message = clone $this->message;
		unset($message->id);

		$sut = $this->getValidateMessageSuccessMock();
		$sut->call('validateMessage', array($message));

		$this->assertNull($message->id);
	}

	/**
	 * @test
	 * @testdox Server::validateMessage() casts object param to array
	 */
	public function validateMessageCastsObjectParamToArray() {
		$message = clone $this->message;
		$expected = array('foo' => 'bar', 'herp' => 'derp');
		$message->params = (object) $expected;

		$sut = $this->getValidateMessageSuccessMock();

		$sut->call('validateMessage', array($message));

		$this->assertEquals($expected, $message->params);

	}

	/**
	 * @test
	 * @testdox Server::validateMessage() sets message's params to array() when missing
	 */
	public function validateMessageSetsParamsWhenMissing() {
		$message = clone $this->message;
		unset($message->params);

		$sut = $this->getValidateMessageSuccessMock();
		$sut->call('validateMessage', array($message));

		$this->assertEquals(array(), $message->params);
	}

	/**
	 * @test
	 * @testdox Server::wrapCallback() returns instance of \Lx\Fna\Wrapper
	 */
	public function wrapCallback() {
		$this->assertInstanceOf(
			'\Lx\Fna\Wrapper',
			$this->obj->call(
				'wrapCallback',
				array(
					function () {
					},
				)
			)
		);
	}

	/**
	 * @test
	 * @testdox Server::handleCallbackResponse() returns proper value
	 */
	public function handleCallbackResponse() {
		$message_id = 'foo';

		$result = 'bar';
		$expected = array(
			'result' => $result,
		);

		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('fault', 'getMessageStub'))
			->getMock();

		$sut->expects($this->once())->method('getMessageStub')->with($message_id)->will(
				$this->returnValue(array())
			);

		$this->assertEquals($expected, $sut->call('handleCallbackResponse', array($result, $message_id)));
	}

	public function getHandleCallbackExceptionMock($message_id, $exception_class) {
		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('fault', 'isExceptionRegistered'))
			->getMock();

		$sut->expects($this->once())->method('fault')->with($this->isInstanceOf($exception_class), $message_id)
			->will($this->returnValue(true));

		return $sut;
	}

	/**
	 * @test
	 * @testdox Server::handleCallbackException() returns proper value on thrown \Lx\JsonRpcXp\Fault
	 */
	public function handleCallbackExceptionOnFault() {
		$message_id = 'foo';
		$exception_class = __NAMESPACE__.'\Fault';
		$sut = $this->getHandleCallbackExceptionMock($message_id, $exception_class);
		$sut->expects($this->never())->method('isExceptionRegistered');

		$this->assertTrue($sut->call('handleCallbackException', array(new $exception_class(), $message_id)));
	}

	/**
	 * @test
	 * @testdox Server::handleCallbackException() returns proper value on thrown registered exception
	 */
	public function handleCallbackExceptionOnRegisteredException() {
		$message_id = 'foo';
		$exception_class = '\Exception';
		$sut = $this->getHandleCallbackExceptionMock($message_id, __NAMESPACE__.'\Fault');
		$sut->expects($this->once())->method('isExceptionRegistered')->will($this->returnValue(true));

		$this->assertTrue($sut->call('handleCallbackException', array(new $exception_class(), $message_id)));
	}

	/**
	 * @test
	 * @testdox Server::handleCallbackException() returns proper value on thrown unregistered exception
	 */
	public function handleCallbackExceptionOnUnregisteredException() {
		$message_id = 'foo';
		$exception_class = '\Exception';
		$sut = $this->getHandleCallbackExceptionMock($message_id, __NAMESPACE__.'\Fault\InternalError');
		$sut->expects($this->once())->method('isExceptionRegistered')->will($this->returnValue(false));

		$this->assertTrue($sut->call('handleCallbackException', array(new $exception_class(), $message_id)));
	}

	/**
	 * @test
	 * @testdox Server::handleMessage() returns result of Server::validateMessage() on validation error
	 */
	public function handleMessageReturnsValidationResultOnError() {
		$message = new \stdClass();

		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('validateMessage'))
			->getMock();

		$sut->expects($this->once())->method('validateMessage')->with($message)->will(
				$this->returnValue('something')
			);

		$this->assertEquals('something', $sut->call('handleMessage', array($message)));
	}


	public function handleMessageProvider() {
		return array(
			array('test_id', 'test_response'), array(null, null),
		);
	}

	/**
	 * @test
	 * @dataProvider handleMessageProvider
	 * @testdox Server::handleMessage() returns proper result on message
	 */
	public function handleMessageReturnsResultOnMessage($message_id, $expected) {
		$message = (object) array('id' => $message_id, 'method' => 'blah', 'params' => array());

		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('validateMessage', 'getCallback', 'handleCallbackResponse', 'invokeCallback', 'handleCallbackException'))
			->getMock();

		$sut->expects($this->once())
			->method('validateMessage')
			->with($message)
			->will($this->returnValue(true));

		$sut->expects($this->once())
			->method('getCallback')
			->with($message->method)
			->will($this->returnValue('callback'));

		$sut->expects($this->once())
			->method('invokeCallback')
			->with('callback', $message->params)
			->will($this->returnValue('response'));

		$sut->expects($this->once())
			->method(('handleCallbackResponse'))
			->with('response')
			->will($this->returnValue($expected));

		$sut->expects($this->never())
			->method(('handleCallbackException'));

		$this->assertEquals($expected, $sut->call('handleMessage', array($message)));
	}

	/**
	 * @test
	 * @dataProvider handleMessageProvider
	 * @testdox Server::handleMessage() returns proper result on fault/exception
	 */
	public function handleMessageReturnsResultOnFault($message_id, $expected) {
		$message = (object) array('id' => $message_id, 'method' => 'blah', 'params' => array());

		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('validateMessage', 'getCallback', 'handleCallbackResponse', 'handleCallbackException', 'invokeCallback'))
			->getMock();

		$sut->expects($this->once())
			->method('validateMessage')
			->with($message)
			->will($this->returnValue(true));

		$sut->expects($this->once())
			->method('getCallback')
			->with($message->method)
			->will($this->returnValue('callback'));

		$sut->expects($this->once())
			->method('invokeCallback')
			->with('callback', $message->params)
			->will($this->throwException(new \Exception()));

		$sut->expects($this->never())
			->method(('handleCallbackResponse'));

		$sut->expects($this->once())
			->method(('handleCallbackException'))
			->with(new \Exception())
			->will($this->returnValue($expected));

		$this->assertEquals($expected, $sut->call('handleMessage', array($message)));
	}

	/**
	 * @test
	 * @testdox Server::handle() returns proper result on json parse error
	 */
	public function handleReturnsFaultOnDecodeError() {
		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('jsonEncode', 'jsonDecode', 'fault'))
			->getMock();

		$request = 'test_request';
		$json_encode_result = 'encode_result';
		$fault_result = 'fault_result';

		$sut->expects($this->once())->method('jsonDecode')->with($request)->will($this->returnValue(false));

		$sut->expects($this->once())->method('fault')->with(
				$this->isInstanceOf(__NAMESPACE__.'\Fault\ParseError')
			)->will($this->returnValue($fault_result));

		$sut->expects($this->once())->method('jsonEncode')->with($fault_result)->will(
				$this->returnValue($json_encode_result)
			);

		$this->assertEquals($json_encode_result, $sut->call('handle', array($request)));
	}

	/**
	 * @test
	 * @testdox Server::handle() returns proper result on single message request
	 */
	public function handleReturnsResponseOnSingleRequest() {
		$request = 'test_request';

		$response = 'test_response';

		$jsondecode_result = (object) array(
			'some' => 'value',
		);

		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('handleMessage', 'jsonEncode', 'jsonDecode'))
			->getMock();

		$sut->expects($this->once())->method('jsonDecode')->with($request)->will(
				$this->returnValue($jsondecode_result)
			);

		$sut->expects($this->once())->method('handleMessage')->with($jsondecode_result)->will(
				$this->returnValue($response)
			);

		$sut->expects($this->once())->method('jsonEncode')->with($response)->will(
				$this->returnValue($response)
			);

		$this->assertEquals($response, $sut->call('handle', array($request)));
	}

	/**
	 * @test
	 * @testdox Server::handle() returns proper result on batch request
	 */
	public function handleReturnsResultsOnBatchMessage() {
		$request = 'test_request';

		$jsondecode_result = array();
		foreach (array('some', 'test', 'values') as $item) {
			$jsondecode_result[] = (object) array(
				'key' => $item
			);
		}

		$response = 'test_response';

		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('handleMessage', 'jsonEncode', 'jsonDecode'))
			->getMock();

		$sut->expects($this->once())->method('jsonDecode')->with($request)->will(
				$this->returnValue($jsondecode_result)
			);

		$sut->expects($this->exactly(count($jsondecode_result)))->method('handleMessage')->with(
				$this->isInstanceOf('\stdClass')
			)->will($this->returnValue($response));

		$sut->expects($this->once())->method('jsonEncode')->with(
				array_pad(array(), count($jsondecode_result), $response)
			)->will($this->returnValue($response));

		$this->assertEquals($response, $sut->call('handle', array($request)));
	}

	/**
	 * @test
	 * @testdox Server::handle() returns null request having no return value (notification)
	 */
	public function handleReturnsNullOnNotifications() {
		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('handleMessage', 'jsonEncode', 'jsonDecode'))
			->getMock();

		$sut->expects($this->once())->method('jsonDecode')->will($this->returnValue(new \stdClass()));

		$sut->expects($this->once())->method('handleMessage')->will($this->returnValue(null));

		$sut->expects($this->never())->method('jsonEncode');

		$this->assertNull($sut->call('handle', array('some_dummy_request')));
	}

	/**
	 * @test
	 * @testdox Server::invokeCallback() invokes callback
	 */
	public function invokeCallback() {
		$this->assertEquals(
			array('re', 'sponse'),
			$this->obj->call('invokeCallback', array(
					function ($params) {
						return $params;
					},
			                array('re', 'sponse')
				)
			)
		);
	}

	/**
	 * @test
	 * @testdox Server::parseRemoteProcedureName() does its work correctly
	 */
	public function parseRemoteProcedureName() {
		$rp_name = 'this.is.the.remote.procedure';
		$result = $this->obj->call('parseRemoteProcedureName', array($rp_name));
		$this->assertEquals(array('this.is.the.remote', 'procedure'), $result);

		$rp_name = 'this_is_the_remote_procedure';
		$result = $this->obj->call('parseRemoteProcedureName', array($rp_name));
		$this->assertEquals(array('', 'this_is_the_remote_procedure'), $result);
	}


	/**
	 * @test
	 * @testdox Server::registerFactory() throws exception on not callable factory
	 *
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage First argument must be callable
	 */
	public function registerFactoryThrowsExceptionOnNonCallable() {
		$this->obj->registerFactory('not callable', 'derp');
	}

	/**
	 * @test
	 * @testdox Server::registerFactory() properly registers factories
	 */
	public function registerFactoryRegistersFactory() {
		$factory = function () {};
		$this->obj->registerFactory($factory, 'herp');
		$this->assertEquals(array('herp' => $factory), $this->obj->get('factories'));
	}

	/**
	 * @test
	 * @testdox Server::resolveFactory() returns false when called on not registered factory
	 */
	public function resolveFactoryReturnsFalseOnMissing() {
		$this->obj->set('factories', array());
		$this->assertFalse($this->obj->call('resolveFactory', array('herp')));
	}

	/**
	 * @test
	 * @testdox Server::resolveFactory() register service instance and unsets registered factory
	 */
	public function resolveFactoryRegistersObject() {
		$namespace = 'herp.derp';
		$factory = 'factory';
		$object = 'object';

		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('invokeCallback', 'registerObject'))
			->getMock();

		$sut->set('factories', array($namespace => $factory));

		$sut->expects($this->once())
			->method('invokeCallback')
			->with($factory)
			->will($this->returnValue($object));


		$sut->expects($this->once())
			->method('registerObject')
			->with($object, $namespace);

		$this->assertInstanceOf(get_class($sut), $sut->call('resolveFactory', array($namespace)));
		$this->assertEquals(array(), $sut->get('factories'));
	}

	/**
	 * @test
	 * @testdox Server::getCallback() resolves factory when method not registered
	 */
	public function getCallbackResolvesFactory() {
		$method = 'namespace.method';
		$parsed = array('namespace', 'method');

		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('parseRemoteProcedureName', 'resolveFactory'))
			->getMock();

		$sut->expects($this->once())
			->method('parseRemoteProcedureName')
			->with($method)
			->will($this->returnValue($parsed));

		$sut->expects($this->once())
			->method('resolveFactory')
			->with($parsed[0]);

		$this->assertFalse($sut->call('getCallback', array($method)));
	}

	/**
	 * @test
	 * @testdox Server::getCallback() returns proper value
	 */
	public function getCallbackReturnsCallback() {
		$method = 'namespace.method';
		$callback = 'callback';


		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\ServerProxy')
			->setMethods(array('parseRemoteProcedureName', 'resolveFactory'))
			->getMock();

		$sut->set('callbacks', array($method => $callback));

		$sut->expects($this->never())
			->method('parseRemoteProcedureName');

		$sut->expects($this->never())
			->method('resolveFactory');

		$this->assertEquals($callback, $sut->call('getCallback', array($method)));
	}
}
