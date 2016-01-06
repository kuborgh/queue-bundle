<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Kuborgh\QueueBundle\Traits;

/**
 * The purpose of this trait is to inject a list of parameters from e.g. parameters.yml.
 */
trait ParameterTrait
{
    /**
     * Parameters saved as key => value pairs
     *
     * @var array
     */
    private $parameters = array();

    /**
     * Add a parameter by id (via DI)
     *
     * @param string $name  Name of the parameter
     * @param mixed  $value Value of the location
     */
    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    /**
     * Get parameter value by name
     *
     * @param String $name Name of the parameter
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getParameter($name)
    {
        if (!$this->hasParameter($name)) {
            throw new \Exception(sprintf('Parameter "%s"', $name), get_class($this));
        }

        return $this->parameters[$name];
    }

    /**
     * Check whether parameter is initialized. Even NULL counts as initialized
     *
     * @param string $name Name of parameter
     *
     * @return bool
     */
    protected function hasParameter($name)
    {
        return array_key_exists($name, $this->parameters);
    }
}
