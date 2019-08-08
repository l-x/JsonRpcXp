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
 * @package Lx\JsonRpcXp
 * @author Alexander Wühr <lx@boolshit.de>
 * @copyright 2014 Alexander Wühr <lx@boolshit.de>
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @link https://github.com/l-x/JsonRpcXp
 */

namespace Lx\JsonRpcXp;

use Fna\Wrapper as CallbackWrapper;

/**
 * Class Server
 *
 * @package Lx\JsonRpcXp
 */
class Server extends Base {

	/**
	 * Array of registered server callbacks
	 *
	 * @var CallbackWrapper[]
	 */
	protected $callbacks = array();

	/**
	 * Array of registered exceptions
	 *
	 * @var \Exception[]
	 */
	protected $registered_exceptions = array();

	/**
	 * Array of registered factory functions
	 *
	 * @var callable[]
	 */
	protected $factories = array();

	/**
	 * Registers a factory function
	 *
	 * This function will be called upon the first call to a method in the given $namespace. The factory function
	 * must return an object instance that can be registered via Server::registerObject()
	 *
	 * @param callable $factory
	 * @param string $namespace
	 *
	 * @return Server
	 */
	public function registerFactory($factory, $namespace = '') {
		if (!is_callable($factory)) {
			throw new \InvalidArgumentException('First argument must be callable');
		}

		$this->factories[$namespace] = $factory;

		return $this;
	}

	/**
	 * Resolves a registered callback an registers the returned object to the server
	 *
	 * @param string $namespace
	 *
	 * @return Server|bool
	 */
	protected function resolveFactory($namespace) {
		if (!isset($this->factories[$namespace])) {
			return false;
		}

		$service = $this->invokeCallback($this->factories[$namespace]);
		$this->registerObject($service, $namespace);
		unset($this->factories[$namespace]);

		return $this;
	}

	/**
	 * Registers an exception
	 *
	 * @param string|string[] $exception_class Exception class or array of exception classes to register
	 * @param int $fault_code Fault code to associate to the exception
	 *
	 * @return Server
	 *
	 * @todo Add check if $exception_classes contains *really* exception classes
	 */
	public function registerException($exception_class) {
		if (is_array($exception_class)) {
			foreach ($exception_class as $class) {
				$this->registerException($class);
			}
		} else if (!is_string($exception_class) || !class_exists($exception_class)) {
			throw new \InvalidArgumentException('Argument must be a valid exception class or array of valid exception classes');
		} else {
			$reflection = new \ReflectionClass($exception_class);
			$this->registered_exceptions[] = $reflection->getName();
			$this->registered_exceptions = array_unique($this->registered_exceptions);
		}

		return $this;
	}

	/**
	 * Determines wether an exception is registered or not
	 *
	 * @param \Exception $e
	 *
	 * @return bool
	 */
	protected function isExceptionRegistered(\Exception $e) {
		return in_array(get_class($e), $this->registered_exceptions);
	}

	/**
	 * Returns a json-rpc error response
	 *
	 * @param Fault $e
	 * @param null|int|string $id
	 *
	 * @return array
	 */
	protected function fault(Fault $e, $id = null) {
		return $this->getMessageStub($id) + array(
			'error' => $e->toArray()
		);
	}

	/**
	 * Registers object or class methods as server callbacks within the given namespace
	 *
	 * @param object|string $object Object instance or class name
	 * @param string $namespace
	 *
	 * @return Server
	 */
	public function registerObject($object, $namespace = '') {
		foreach (get_class_methods($object) as $method_name) {
			$this->registerFunction($method_name, array($object, $method_name), $namespace);
		}

		return $this;
	}

	/**
	 * Generates the remote procedure name for a method or function
	 *
	 * @param $method_name
	 * @param string $namespace
	 *
	 * @return string
	 */
	protected function getRemoteProcedureName($method_name, $namespace = '') {
		if ($namespace) {
			$name = "$namespace.$method_name";
		} else {
			$name = $method_name;
		}

		return $name;
	}

	/**
	 * Splits a remote procedure name to the namespace and method name
	 *
	 * @param string $remote_procedure_name
	 *
	 * @return array
	 */
	protected function parseRemoteProcedureName($remote_procedure_name) {
		$parts = explode('.', $remote_procedure_name);
		$method_name = array_pop($parts);
		$namespace = join('.', $parts);

		return array($namespace, $method_name);
	}


	/**
	 * Wraps the callback for proper argument handling
	 * @see https://github.com/l-x/Fna
	 *
	 * @param callable $callback
	 *
	 * @return CallbackWrapper
	 */
	protected function wrapCallback($callback) {
		return new CallbackWrapper($callback);
	}

	/**
	 * Registers a callback as server callback within the given namespace
	 *
	 * @param string $name The name under which the callback will be accessible via json-rpc
	 * @param callable $callback The callback wich will be exposed to json-rpc API
	 * @param string $namespace The namespace under which the callback will be mounted
	 *
	 * @return Server
	 */
	public function registerFunction($name, $callback, $namespace = '') {
		$this->callbacks[$this->getRemoteProcedureName($name, $namespace)] = $this->wrapCallback($callback);

		return $this;
	}

	/**
	 * Validates a json-rpc request message, returns a fault array on error or true on success
	 *
	 * @param \stdClass $message
	 *
	 * @return array|bool
	 */
	protected function validateMessage(\stdClass $message) {
		if (!isset($message->id)) {
			$message->id = null;
		}

		if (!isset($message->jsonrpc) || $message->jsonrpc !== self::JSONRPC_VERSION) {
			return $this->fault(new Fault\InvalidRequest('Wrong or missing json-rpc version string '), $message->id);
		}

		if (!isset($message->method)) {
			return $this->fault(new Fault\InvalidRequest('Missing method name'), $message->id);
		}

		if (!$this->getCallback($message->method)) {
			return $this->fault(new Fault\MethodNotFound(strval($message->method)), $message->id);
		}

		if (!isset($message->params)) {
			$message->params = array();
		}

		if (is_object($message->params)) {
			$message->params = (array) $message->params;
		}

		if (!is_array($message->params)) {
			return $this->fault(new Fault\InvalidParams(), $message->id);
		}

		return true;
	}

	/**
	 * Handles an exception thrown by callback
	 *
	 * @param \Exception $e
	 * @param int|string|float $message_id
	 *
	 * @return void
	 */
	protected function handleCallbackException(\Exception $e, $message_id) {
		if ($e instanceof \Lx\JsonRpcXp\Fault) {
			$exception = $e;
		} else if ($this->isExceptionRegistered($e)) {
			$exception = Fault::hydrate($e);
		} else {
			$exception = new Fault\InternalError();
		}

		return $this->fault($exception, $message_id);
	}

	/**
	 * Handles the callback's response
	 *
	 * @param mixed $response
	 * @param null|int|string $message_id
	 *
	 * @return array
	 */
	protected function handleCallbackResponse($response, $message_id) {
		return $this->getMessageStub($message_id) + array(
			'result'        => $response,
		);
	}

	/**
	 * Invokes the callback
	 *
	 * @param callable $callback
	 * @param array $params
	 *
	 * @return mixed
	 */
	protected function invokeCallback($callback, $params = array()) {
		$result = call_user_func($callback, $params);

		return $result;
	}

	/**
	 * Returns a callback by the method name, resolves the registered factory if available
	 *
	 * @param string $method The fully qualified remote procedure name
	 *
	 * @return bool|\Lx\Fna\Wrapper
	 */
	protected function getCallback($method) {
		$callback = false;

		if (!isset($this->callbacks[$method])) {
			list($namespace, $_) = $this->parseRemoteProcedureName($method);
			$this->resolveFactory($namespace);
		}

		if (isset($this->callbacks[$method])) {
			$callback = $this->callbacks[$method];
		}

		return $callback;
	}

	/**
	 * Executes a single request message and returns the result or a fault message
	 *
	 * @param \stdClass $message
	 *
	 * @return array
	 */
	protected function handleMessage(\stdClass $message) {
		$validation_result = $this->validateMessage($message);

		if ($validation_result !== true) {
			return $validation_result;
		}

		$callback = $this->getCallback($message->method);

		try {
			$response = $this->handleCallbackResponse(
				$this->invokeCallback(
					$callback,
					$message->params
				),
				$message->id
			);
		} catch (\Exception $e) {
			$response = $this->handleCallbackException($e, $message->id);
		}

		if (!is_null($message->id)) {
			return $response;
		}

		return null;
	}

	/**
	 * Handles the raw json request (batch or single) and returns the json-encoded response
	 *
	 * @param string|null $request Optional json request object or array
	 *
	 * @return string Json encoded response
	 */
	public function handle($request) {
		if (!$data = $this->jsonDecode($request)) {
			return $this->jsonEncode(
				$this->fault(new Fault\ParseError())
			);
		}

		if (is_array($data)) {
			$result = array();
			foreach ($data as $message) {
				if ($response = $this->handleMessage($message)) {
					$result[] = $response;
				}
			}
		} else {
			$result = $this->handleMessage($data);
		}

		if ($result) {
			return $this->jsonEncode($result);
		}

		return null;
	}
}

