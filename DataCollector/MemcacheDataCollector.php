<?php
namespace Lsw\MemcacheBundle\DataCollector;

use Symfony\Component\Yaml\Yaml;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Lsw\MemcacheBundle\Cache\LoggingMemcache;


/**
 * MemcacheDataCollector
 *
 * @author Maurits van der Schee <m.vanderschee@leaseweb.com>
 */
class MemcacheDataCollector extends DataCollector
{
    private $clients;
    private $options;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->clients = array();
        $this->options = array();
    }

    /**
     * Add a Memcache object to the collector
     *
     * @param string          $name     Name of the Memcache client
     * @param array           $options  Options for Memcache client
     * @param LoggingMemcache $memcache Logging Memcache object
     *
     * @return void
     */
    public function addClient($name, $options, LoggingMemcache $memcache)
    {
        $this->clients[$name] = $memcache;
        $this->options[$name] = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $empty = array('calls'=>array(),'config'=>array(),'options'=>array(),'statistics'=>array());
        $this->data = array('clients'=>$empty,'total'=>$empty);
        foreach ($this->clients as $name => $memcache) {
            $calls = $memcache->getLoggedCalls();
            $this->data['clients']['calls'][$name] = $calls;
            $this->data['clients']['options'][$name] = $this->options[$name];
        }
        $this->data['clients']['statistics'] = $this->calculateStatistics($this->data['clients']['calls']);
        $this->data['total']['statistics'] = $this->calculateTotalStatistics($this->data['clients']['statistics']);
    }

    private function calculateStatistics($calls)
    {
        $statistics = array();
        foreach ($this->data['clients']['calls'] as $name => $calls) {
            $statistics[$name] = array('calls'=>0,'time'=>0,'reads'=>0,'hits'=>0,'misses'=>0,'writes'=>0);
            foreach ($calls as $call) {
                $statistics[$name]['calls'] += 1;
                $statistics[$name]['time'] += $call->time;
                if ($call->name == 'get') {
                    $statistics[$name]['reads'] += 1;
                    if ($call->result !== false) {
                        $statistics[$name]['hits'] += 1;
                    } else {
                        $statistics[$name]['misses'] += 1;
                    }
                } elseif ($call->name == 'get') {
                    $statistics[$name]['writes'] += 1;
                }
            }
            if ($statistics[$name]['reads']) {
                $statistics[$name]['ratio'] = 100*$statistics[$name]['hits']/$statistics[$name]['reads'].'%';
            } else {
                $statistics[$name]['ratio'] = 'N/A';
            }
        }

        return $statistics;
    }

    private function calculateTotalStatistics($statistics)
    {
        $totals = array('calls'=>0,'time'=>0,'reads'=>0,'hits'=>0,'misses'=>0,'writes'=>0);
        foreach ($statistics as $name => $values) {
            foreach ($totals as $key => $value) {
                $totals[$key] += $statistics[$name][$key];
            }
        }
        if ($totals['reads']) {
            $totals['ratio'] = 100*$totals['hits']/$totals['reads'].'%';
        } else {
            $totals['ratio'] = 'N/A';
        }

        return $totals;
    }

    /**
     * Method returns amount of logged Memcache reads: "get" calls
     *
     * @return number
     */
    public function getStatistics()
    {
        return $this->data['clients']['statistics'];
    }

    /**
     * Method returns the statistic totals
     *
     * @return number
     */
    public function getTotals()
    {
        return $this->data['total']['statistics'];
    }


    /**
     * Method returns all logged Memcache call objects
     *
     * @return mixed
     */
    public function getCalls()
    {
        return $this->data['clients']['calls'];
    }

    /**
     * Method returns all Memcache options
     *
     * @return mixed
     */
    public function getOptions()
    {
        return $this->data['clients']['options'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'memcache';
    }
}
