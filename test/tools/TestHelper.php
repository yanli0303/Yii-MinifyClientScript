<?php

/**
 * @author Yan Li <peterleepersonal@gmail.com>
 */
class TestHelper
{
    /**
     * Make use of PHP reflection, invoke a "private" or "protected" method of specifiled class instance.
     * Usually it's not necessary to test the "private" or "protected" methods.
     * Unless you have overriden such a method of framework.
     *
     * @param object $classInstance the class instance whose method will be invoked
     * @param string $methodName    the name of the method
     * @param array  $arguments     the arguments for the method
     *
     * @return mixed returns the return value of the method
     */
    public static function invokeProtectedMethod($classInstance, $methodName, $arguments = array())
    {
        $className = get_class($classInstance);
        $class = new ReflectionClass($className);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);

        if (empty($arguments)) {
            return $method->invoke($classInstance);
        } else {
            array_unshift($arguments, $classInstance);

            return call_user_func_array(array($method, 'invoke'), $arguments);
        }
    }

    /**
     * Make use of PHP reflection, invoke a static "private" or "protected" method of specifiled class.
     * Usually it's not necessary to test the "private" or "protected" methods.
     * Unless you have overriden such a method of framework.
     *
     * @param string $className  the class name
     * @param string $methodName the name of the method
     * @param array  $arguments  the arguments for the method
     *
     * @return mixed returns the return value of the method
     */
    public static function invokeStaticProtectedMethod($className, $methodName, $arguments = array())
    {
        $class = new ReflectionClass($className);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);

        if (empty($arguments)) {
            return $method->invoke(null);
        } else {
            array_unshift($arguments, null);

            return call_user_func_array(array($method, 'invoke'), $arguments);
        }
    }

    /**
     * Get value of private and protected properties of an object.
     *
     * @param object $classInstance a class instance.
     * @param string $propertyName  property name
     *
     * @return mixed returns the property value.
     */
    public static function getProtectedProperty($classInstance, $propertyName)
    {
        $instance = new ReflectionObject($classInstance);
        $prop = $instance->getProperty($propertyName);
        $prop->setAccessible(true);

        return $prop->getValue($classInstance);
    }

    /**
     * Set new value for a private and protected property of an object.
     *
     * @param object $classInstance a class instance.
     * @param string $propertyName  property name
     * @param mixed  $newValue      new value to set
     *
     * @return mixed returns the property value.
     */
    public static function setProtectedProperty($classInstance, $propertyName, $newValue)
    {
        $instance = new ReflectionObject($classInstance);
        $prop = $instance->getProperty($propertyName);
        $prop->setAccessible(true);

        return $prop->setValue($classInstance, $newValue);
    }

    /**
     * Gets static property value.
     *
     * @param string $className    class name
     * @param string $propertyName property name
     *
     * @return mixed returns the static property value
     */
    public static function getStaticPropertyValue($className, $propertyName)
    {
        $ref = new ReflectionClass($className);

        return $ref->getStaticPropertyValue($propertyName);
    }

    /**
     * Sets static property value.
     *
     * @@param string $className class name
     * @param string $propertyName property name
     * @param mixed  $newValue     New property value.
     */
    public static function setStaticPropertyValue($className, $propertyName, $newValue)
    {
        $ref = new ReflectionClass($className);
        $prop = $ref->getProperty($propertyName);
        $prop->setAccessible(true);

        return $prop->setValue($newValue);
    }
}
