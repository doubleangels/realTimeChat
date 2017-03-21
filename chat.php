#!/usr/local/bin/php

<?php
// Require in composer and config dependencies.
require 'vendor/autoload.php';
$ini = parse_ini_file('config.ini');

// Set namespace and start a new Pubnub instance.
use Pubnub\Pubnub;
$pubnub = new Pubnub(
    $ini['publish_key'],
    $ini['subscribe_key'],
    $ini['secret_key'],
    false
);

// Prompt user for chat room choice and username.
// Connect to chosen room, get a list of usernames 
// in that room, and make sure the chosen username 
// isn't already taken.
fwrite(STDOUT, "Join room: ");
$room = trim(fgets(STDIN));
$hereNow = $pubnub->hereNow($room, false, true);
function connectAs() {
    global $hereNow;
    fwrite(STDOUT, "Connect as: ");
    $username = trim(fgets(STDIN));
    foreach ($hereNow['uuids'] as $user) {
        if ($user['state']['username'] === $username) {
            fwrite(STDOUT, "Username taken!\n");
            $username = connectAs();
        }
    }
    return $username;
};
$username = connectAs();
$pubnub->setState($room, ['username' => $username]);
fwrite(STDOUT, "\nConnected to  '{$room}' as '{$username}'.\n");

// Constantly check for messages to be sent or recieved.
// If found, display it to the user with the sender and timestamp information.
$pid = pcntl_fork();
if ($pid == -1) {
    exit(1);
} else if ($pid) {
    fwrite(STDOUT, "> ");
    while (true) {
        $message = trim(fgets(STDIN));
        $pubnub->publish($room, [
            'text' => $message,
            'username' => $username,
        ]);
    }
    pcntl_wait($status);
} else {
    $pubnub->subscribe($room, function($payload) use ($username) {
    $timestamp = date('d-m-y H:i:s');
    if ($username != $payload['message']['username']) {
        fwrite(STDOUT, "\r");
    }
    fwrite(STDOUT, "[{$timestamp}] <{$payload['message']['username']}> {$payload['message']['text']}\n");
    fwrite(STDOUT, "\r> ");
    return true;
    });
}
