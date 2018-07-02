# firebase-notification
Firebase Notifications in PHP

This class supports sending firebase notifications at once to the android as well as iOS devices.

## Usage

###Initialize the class

```
$notification = new Notification($serverKey);
```

### calling for single notification to Android

```
$notification->setSubject($subject)
            ->setText($text)
            ->sendToAndroid($firebaseToken);
```

##Below code send multiple notifications at once, so it needs the input like below:

```
$tokens = array(
    array(
        'firebase_token' => 'your_firebase_token',
        'id' => 'Unique ID', //Either user ID or Device ID or anything which is relevant to your application.
        'device_type' => 'A/I' // Either A or I, Android and IOS respectively.
    ),
);
```
### calling for multiple notifications to Android and iOS

```
$notification->setReceivers($tokens)
            ->setSubject($subject)
            ->setText($text)
            ->sendMultiple();
```

### calling for multiple notifications to Android only

```
$notification->setReceivers($tokens)
            ->setSubject($subject)
            ->setText($text)
            ->sendMultipleToAndroid();
```

### calling for multiple notifications to iOS only

```
$notification->setReceivers($tokens)
            ->setSubject($subject)
            ->setText($text)
            ->sendMultipleToIphone();
```
