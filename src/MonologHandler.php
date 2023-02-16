<?php

namespace Rollbar\Laravel;

use Monolog\Handler\RollbarHandler;
use Monolog\LogRecord;

class MonologHandler extends RollbarHandler
{
    protected $app;

    /**
     * @param $app
     * @return void
     */
    public function setApp($app)
    {
        $this->app = $app;
    }

    /**
     * @param array|LogRecord $record
     * @return void
     */
    protected function write(array|LogRecord $record): void
    {
        if($record instanceof LogRecord) {
            // We need to create a new instance of LogRecord because context is a readonly value
            parent::write(new LogRecord($record->datetime,
                $record->channel,
                $record->level,
                $record->message,
                $this->addContext($record->context),
                $record->extra,
                $record->formatted));
        } else {
            $record['context'] = $this->addContext($record['context']);
            parent::write($record);
        }
    }

    /**
     * @param array $context
     * @return array
     */
    protected function addContext(array $context = []): array
    {
        $config = $this->rollbarLogger->extend([]);

        if (empty($config['person']) || ! is_array($config['person'])) {
            $person = [];
        } else {
            $person = $config['person'];
        }

        // Merge person context.
        if (isset($context['person']) && is_array($context['person'])) {
            $person = $context['person'];
            unset($context['person']);
        } elseif (isset($config['person_fn']) && is_callable($config['person_fn'])) {
            $data = @call_user_func($config['person_fn']);
            if (! empty($data)) {
                if (is_object($data)) {
                    if (isset($data->id)) {
                        $person['id'] = $data->id;
                    } elseif (method_exists($data, 'getKey')) {
                        $person['id'] = $data->getKey();
                    }

                    if (isset($person['id'])) {
                        if (isset($data->username)) {
                            $person['username'] = $data->username;
                        }
                        if (isset($data->email)) {
                            $person['email'] = $data->email;
                        }
                    }
                } elseif (is_array($data) && isset($data['id'])) {
                    $person = $data;
                }
            }
            unset($data);
        }

        // Add session data.
        if (isset($this->app->session) && $session = $this->app->session->all()) {
            // Add user session information.
            if (isset($person['session'])) {
                $person['session'] = array_merge($session, $person['session']);
            } else {
                $person['session'] = $session;
            }

            // User session id as user id if not set.
            if (! isset($person['id'])) {
                $person['id'] = $this->app->session->getId();
            }
        }

        $this->rollbarLogger->configure(['person' => $person]);

        return $context;
    }
}
