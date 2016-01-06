<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Traits;

/**
 * This trait helps injecting the parameter "max_concurrency" into the model
 */
trait ConcurrencyTrait
{
    /**
     * Maximum number of concurrent jobs
     *
     * @var Int
     */
    protected $concurrency = 1;

    /**
     * Inject parameter
     *
     * @param Int $concurrency
     */
    public function setConcurrency($concurrency)
    {
        $this->concurrency = $concurrency;
    }

    /**
     * Get number of allowed concurrent jobs
     *
     * @return Int
     */
    public function getConcurrency()
    {
        return $this->concurrency;
    }


}
