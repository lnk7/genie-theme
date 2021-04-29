<?php

namespace Theme\Handlers;

use Lnk7\Genie\Utilities\SendEmail;
use Lnk7\Genie\View;
use Monolog\Handler\AbstractProcessingHandler;

class WordpressEmailHandler extends AbstractProcessingHandler
{

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  $record []
     *
     * @return void
     */
    protected function write(array $record): void
    {


        $email = View::with('admin/emails/error.twig')
            ->addVar('errorData', json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            ->render();

        SendEmail::to('sunil@cote.co.uk')
            ->subject('[LOG] ' . $record['message'])
            ->body($email)
            ->send();
    }
}
