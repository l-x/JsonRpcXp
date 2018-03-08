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
/**
 * Created by PhpStorm.
 * User: awuehr
 * Date: 10.02.14
 * Time: 07:24
 */

namespace Lx\JsonRpcXp;
require_once __DIR__.'/../vendor/autoload.php';

class FaultProxy extends Fault {
	public function _set($key, $value) {
		$this->$key = $value;

		return $this;
	}

	public function _get($key) {
		return $this->$key;
	}
}


class FaultTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var Fault
	 */
	protected $obj;

	public function setUp() {
		$this->obj = new FaultProxy();
	}

	/**
	 * @test
	 * @testdox Fault::__construct() calls Fault::setData()
	 */
	public function constructorCallsSetData() {
		$sut = $this->getMockBuilder(__NAMESPACE__.'\FaultProxy', array('setData'))->getMock();
		$sut->expects($this->once())
			->method('setData')
			->with('foo')
			->will($this->returnValue($sut))
		;

		$sut->__construct('', 0, 'foo');
	}

	/**
	 * @test
	 * @testdox Fault:setData() sets property correct
	 */
	public function setData() {
		$data = 'test data';
		$this->obj->setData($data);
		$this->assertEquals($data, $this->obj->_get('data'));
	}

	/**
	 * @test
	 * @testdox Fault::getData() returns correct property
	 */
	public function getData() {
		$data = 'test_data';
		$this->obj->_set('data', $data);
		$this->assertEquals($data, $this->obj->getData());
	}

	/**
	 * @test
	 * @testdox Fault::hydrate() hydrates and returns correct instance from exception
	 */
	public function hydrate() {
		$message = 'test message';
		$code = 1337;

		$exception = new \Exception($message, $code);

		$fault = $this->obj->hydrate($exception);

		$this->assertInstanceOf(__NAMESPACE__.'\Fault', $fault);
		$this->assertEquals(-32000-$code, $fault->getCode());
		$this->assertEquals($message, $fault->getMessage());

	}

	/**
	 * @test
	 */
	public function toArrayReturnsStructWithoutAdditionalData() {
		$message = 'test message';
		$code = 1337;

		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\FaultProxy')
			->setMethods(array('getData'))
			->setConstructorArgs(array($message, $code))
			->getMock();

		$sut->expects($this->once())
			->method('getData')
			->will($this->returnValue(null))
		;


		$expected = array(
			'message'       => $message,
		        'code'          => $code,
		);

		$this->assertEquals($expected, $sut->toArray());
	}

	/**
	 * @test
	 */
	public function toArrayReturnsStructWithAdditionalData() {
		$message = 'test message';
		$code = 1337;
		$data = 'foo';

		$sut = $this
			->getMockBuilder(__NAMESPACE__.'\FaultProxy')
			->setMethods(array('getData'))
			->setConstructorArgs(array($message, $code))
			->getMock();

		$sut->expects($this->once())
			->method('getData')
			->will($this->returnValue($data))
		;


		$expected = array(
			'message'       => $message,
			'code'          => $code,
		        'data'          => $data,
		);

		$this->assertEquals($expected, $sut->toArray());
	}

}
