3# Sesshin

Object-oriented, extendable advanced session handling component written with
security in mind that mitigates attacks like Session Hijacking, Session Fixation,
Session Exposure, Sesion Poisoning, Session Prediction.

One of previous versions of this lib has been awarded 1st place in
[php.pl contest](http://wortal.php.pl/phppl/Wortal/Spolecznosc/Konkursy/Konkurs-Pozyteczne-i-praktyczne-biblioteki-Wyniki).

Features:

* smart session expiry control
* prevents session adoption, i.e. session ids generated only by the component
  are acceptable (strict model)
* sends cookie only when session really created
* session id rotation (anti session hijacking), based on time and/or number of
  requests
* support for user-defined storage
* support for user-defined listeners (observers)
* support for user-defined entropy callback
* support for own fingerprint generators, e.g. user agent,
* unlike PHP native mechanism, you don't have to use cron or resourse-consuming
  100% garbage collecting probability to ensure sessions are removed exactly
  after specified time
* convention over configuration (has defined default listener, storage, entropy
  generator, fingerprint generator and ID storage)
* 100% independent from insecure native PHP session extension
* and some more...

[![Build Status](https://travis-ci.org/sobstel/sesshin.png?branch=master)](https://travis-ci.org/sobstel/sesshin)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/sobstel/sesshin/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sobstel/sesshin/?branch=master)

## Usage

### Installation

Use composer: https://packagist.org/packages/sobstel/sesshin

### Create new session

Only when `create()` called, session cookie is created (for native PHP session
handler cookie is present all the time whether it's needed or not).

```php
$session->create();
```

### Open existing session

If session was not created earlier, session is not opened and `false` is returned.

```php
$session->open();
```

If you want to create new session if it does not exist already, just pass `true`
as argument. It will call `create()` transparently.

```php
$session->open(true);
```

### Regenerate session id

```php
// auto-regenerate after specified time (secs)
$session->setIdTtl(300);

// auto-regenerate after specified number of requests
$session->setIdRequestsLimit(10);

// manually
$session->regenerateId();
```

### Listen special events

```php
use Sesshin\Event;

$eventEmitter = $session->getEventEmitter();

$eventEmitter->addListener(Event::NO_DATA_OR_EXPIRED, function(Event $event) {
  die('Session expired or session adoption attack!');
});
$eventEmitter->addListener(Event::NO_DATA_OR_EXPIRED, function(Event $event) {
  die(sprintf('Session %s expired!', $session->getId()));
});
$eventEmitter->addListener(Event::INVALID_FINGERPRINT, function(Event $event) {
  die('Invalid fingerprint, possible attack!');
});
```

### User session

```php
use Sesshin\Session\User;

$user_session = new User();

$user_session->create();
$user_session->login(1);

if ($user_session->isLogged()) {
  $user = UserRepository::find($user_session->getUserId());
  echo sprintf('User %s is logged', $user->getUsername());
}
```

### Change storage

```php
use Sesshin\Storage\Memcache;

$session->setStorage(new Memcache($memcache_driver));
```

### Change entropy algorithm

Entropy is used to generate session id.

```php
$session->getIdHandler()->setEntropyGenerator(new MyFancyEntropyGenerator());
```

`MyFancyEntropyGenerator` must implement `Sesshin\EntropyGenerator\EntropyGeneratorInterface`.

### Change session ID storage

By default session ID is stored in cookie, but sometimes you may need to force
session id, eg. based on some token, query string var, etc.

```php
$session->getIdHandler()->setIdStorage(new MyFancyIdStorage());
```

`MyFancyIdStorage` must implement `Sesshin\Id\Storage\StorageInterface`.
