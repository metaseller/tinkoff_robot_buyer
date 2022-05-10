# tinkoff-robot-ETF-buyer-php
Проект сделан для демонстрации базовых возможностей работы с 
Tinkoff Invest Api 2 (https://www.tinkoff.ru/invest/open-api/) через PHP ради фана, а также для участия в конкурсе 
Tinkoff Invest Robot Сontest (https://meetup.tinkoff.ru/event/tinkoff-invest-robot-contest/, https://t.me/tinkoff_invest_robot_contest)

# Введение

Вторая версия API Tinkoff Invest в настоящее время позиционируется как gRPC-интерфейс для взаимодействия с торговой платформой
Тинькофф Инвестиции. 

В настоящее доступны официальные SDK для популярных языков программирования,
таких как python (https://github.com/Tinkoff/invest-python), java (https://github.com/Tinkoff/invest-api-java-sdk), 
csharp (https://github.com/Tinkoff/invest-api-csharp-sdk). 

Я решил для простоты работы сделать подобный неофициальный SDK для PHP7 (https://github.com/metaseller/tinkoff-invest-api-v2-php) 
и обертку вокруг него для популярного фреймворка Yii 2 Framework (https://github.com/metaseller/tinkoff-invest-api-v2-yii2).

Целью было снизить порог входа и облегчить жизнь для PHP программистов. Фреймфорки 
выполнены в виде библиотек и доступны для очень простой установки через composer.

Для Tinkoff Invest Robot Сontest было решено на базе SDK создать простенький проект на Yii2 Framework, 
использующий реализованный Unofficial PHP SDK. Проект работает как консольное приложение на
Yii2 Framework, демонстрирует простейший базовый функционал (как подключиться к API, как запросить данные об аккаунте, 
как получить информацию о составе портфеля, как вывести историю пополнения портфеля, как подписаться на "стакан", а также 
реализующий логику простейшего торгового робота-покупателя).

# Логика работы робота-покупателя

Реализованный робот НЕ является роботом-скальпером, который зарабатывает копеечку на разнице между покупкой и продажей. 
Он робот-покупатель :) 

Все инвесторы знают, что одна из самых правильных инвестиционных стратегий - это регулярно покупать и держать :)
Наш робот доведет эту мысль до абсолюта :) 

Для примера реализации робот будет регулярно покупать указанный в конфиге ETF, используя вольную реализацию алгоритма 
Trailing Buy (перефразированный Traling Stop :)), постоянно готовясь к набору позиции и покупая на росте или на отскоке 
на определенную дельту от локального минимума. 

Выбрана простая идея для реализации простой стратегии, не требующей даже подключения СУБД. 
Робот также не использует стримы, ему достаточно регулярного выполнения по cron.

<b>Как работает робот:</b>

Робот конфигурируется следующими параметрами: 

````
[
    'ETF' => [
        'TMOS' => [
            'ACTIVE' => true,
            'INCREMENT_VALUE' => 1, // На сколько мы увеличиваем накопленное количество лотов позиций к покупке через каждый период
            'INCREMENT_PERIOD' => 10, // Период в минутах, через который мы инкрементируем количество лотов позиций к покупке
            'BUY_CHECK_PERIOD' => 1, // Период в минутах, через который мы проверяем возможность покупки накопленного количества лотов позиций
            'BUY_LOTS_BOTTOM_LIMIT' => 5, // Не пытаемся совершить покупку, пока не достигнут указанный накопленный лимит лотов к покупке
            'BUY_TRAILING_PERCENTAGE' => 0.09, //Величина в процентах, на которую текущая цена должна превысить трейлинг цену для совершения покупки
        ],
    ],
];
````

Робот покупает ETF #TMOS (https://www.tinkoff.ru/invest/etfs/TMOS/) регулярно по следующей логике: 

1) Есть "накопленное количество лотов к покупке". В начале каждого торгового для оно равно 0. Затем каждые 10 минут (INCREMENT_PERIOD) с начала торгов к этому значению добавляется +1 лот (INCREMENT_VALUE). Это делается отдельной консольной командой, вызываемое по CRON.
2) Одновременно с инкрементом запоминается последняя цена (лучший ASK по стакану). Как только "накопленное количество лотов к покупке" стало больше или равно значения BUY_LOTS_BOTTOM_LIMIT последняя цена фиксируется. 
3) Параллельно каждую 1 минуту (BUY_CHECK_PERIOD) отдельной консольной командой вызывается скрипт, который, в случае, если "накопленное количество лотов к покупке" >= BUY_LOTS_BOTTOM_LIMIT сравнивает последнюю зафиксированную цену с текущим лучшим ASK по стакану. Если лучший ASK меньше, чем последнее запомненное значение (цена падает), то запомненное значение обновляется (становится Traling ценой), и бот ожидает дальнейшего движения цены. 
4) Как только лучший ASK в стакане стал больше либо равен значения Trailing цены на BUY_TRAILING_PERCENTAGE процентов, происходит попытка покупки по лучшей цене (лучший ASK в стакане) путем выставления лимитной заявки на покупку "накопленного количество лотов к покупке".   
5) За небольшое количество времени до конца торгов (значение захардкожено в коде), если текущее "накопленное количество лотов к покупке" > BUY_LOTS_BOTTOM_LIMIT / 2, то форсировано выставляется лимитная заявка на покупку "накопленного количество лотов к покупке" по цене "лучший ASK в стакане". 

Вот такая незамысловатая стратегия регулярной покупки. 
Лимитная заявка на "Лучший ASK в стакане" и "небольшие" тестовые объемы практически гарантируют выполнение заявки. 

Пункты 1-2 обрабатываются действием `actionIncrementEtfTrailing` (https://github.com/metaseller/tinkoff-robot-buyer/blob/main/commands/TinkoffInvestController.php#L175) консольного контролера Yii2 `TinkoffInvestController`.
Пункты 3-5 обрабатываются действием `actionBuyEtfTrailing` (https://github.com/metaseller/tinkoff-robot-buyer/blob/main/commands/TinkoffInvestController.php#L268) консольного контролера Yii2 `TinkoffInvestController`.

Для реализации выбран популярный фреймворк Yii2. 
Все действия робота, вызовы фоновых заданий по CRON, ошибки API запросов 
логируются в стандартной папке (`./runtime/logs`) в соответствующем лог-файле, а также выводятся в stdout. 

Вы можете следить за ходом работы своего робота через соответствующий лог файл (например открыв его командой `tail -f tinkoff_invest_strategy.log`) 
или переопределить логику обработчика ошибок и самостоятельно добавить туда отправку нотификации о событиях или неполадках себе через email/telegram или как-то иначе.

Наличие денег для выставления заявки роботом не контролируется. Робот играется сейчас на 
аккаунте, где выключена маржинальная торговля (с ней робот НЕ тестировался), и если средств для выставления заявки не хватает, 
то робот логирует соответствующее сообщение об ошибке выставления заявки и покупка просто не происходит. 

# Технические требования к запуску проекта

Проект сейчас запущен на хостинге VPS Timeweb (НЕ РЕКЛАМА!). Чтобы поиграться самому вам потребуется следующее: 

Для начала работы нам потребуется:
* PHP 7.4 или новее (я делал и тестировал на php 7.4 / Ubuntu 18.04.5)
* PECL, Composer версии 2+.

Как установить и настроить composer, если его нет: https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos
Лучше делать 'глобальную установку'. 

Проект использует зависимость от проектов (https://github.com/metaseller/tinkoff-invest-api-v2-yii2, https://github.com/metaseller/tinkoff-invest-api-v2-php). 

---
СПРАВКА: Моя неофициальная SDK-шка `tinkoff-invest-api-v2-php` содержит уже сгенерированные из proto файлов модели.
Просто для информации, если вы захотите собрать проект из proto-файлов с официального репозитория (https://github.com/Tinkoff/investAPI/), то вам понадобиться

1) Установить protoc
2) Собрать плагин grpc_php_plugin (см https://grpc.io/docs/languages/php/basics/#setup)
3) Вызвать что-нибудь типа:
4) 
```
sudo protoc --proto_path=~/contracts_dir/ --php_out=~/models_dic/ --grpc_out=~/models_dir/ --plugin=protoc-gen-grpc=./grpc_php_plugin ~/contracts_dir/*
```
подставив нужные вам директории (для запуска проекта этого НЕ требуется).

---

Далее нам понадобится расширение grps.so для PHP (https://cloud.google.com/php/grpc).
```
sudo pecl install grpc
```
а после не забываем в php.ini добавить
```
extension=grpc.so
```

А если вам необходимо логгировать исполнение, то можно также добавить в php.ini
```
grpc.grpc_verbosity=debug
grpc.grpc_trace=all,-polling,-polling_api,-pollable_refcount,-timer,-timer_check
grpc.log_filename=/var/log/grpc.log
```

Само собой не забыть
```
sudo touch /var/log/grpc.log
sudo chmod 666 /var/log/grpc.log
```

Также отмечу, что я в качестве кеша использовал у себя локально Redis, которые настроил доступным с вот таким конфигом:
```
[
    'hostname' => 'localhost',
    'port' => 6379,
    'database' => 0,
]
```

если Вам неохото возиться с Redis, вы можете в конфигах Yii2 (см https://github.com/metaseller/tinkoff-robot-buyer/blob/main/config/console.php#L46, https://github.com/metaseller/tinkoff-robot-buyer/blob/main/config/web.php#L51) 
заменить сервис кеша на `yii\caching\FileCache` (https://www.yiiframework.com/doc/api/2.0/yii-caching-filecache), не забыв выпилить из конфига
Redis (https://github.com/metaseller/tinkoff-robot-buyer/blob/main/config/web.php#L40).

PS: Ну и в целом, рано или поздно придется ознакомиться с документацией по GRPC: 

1) Quick start with PHP -> https://grpc.io/docs/languages/php/quickstart/
2) Basic tutorials -> https://grpc.io/docs/languages/php/basics/

PSS: Вот здесь вы можете видеть общий список используемых для запуска проекта зависимостей: https://packagist.org/packages/metaseller/tinkoff-robot-buyer 

# Пошаговая установка и запуск проекта

1) Будем устанавливать используя composer. 

На подготовленном сервере переходим в нужную нам папку
```
cd /var/www/
```
и выполняем команду: 
```
composer create-project --prefer-dist --stability=dev metaseller/tinkoff-robot-buyer contest.metaseller.local
```
в папку `contest.metaseller.local` будет скачан/установлен проект и подтянуты автоматически все зависимости.

Заходим в эту папку 
```
cd contest.metaseller.local
```
и выполняем команду 
```
./init
```
если не выполняется - не забываем сделать 
```
sudo chmod 755 init
```
инициализируем dev окружение 
```
Yii Application Initialization Tool v1.0

Which environment do you want the application to be initialized in?

  [0] dev
  [1] prod

  Your choice [0-1, or "q" to quit] 0
```
далее заходим в директорию `config` и настраиваем свое окружение: 
```
vim credentials.php 
```
(кого пугает vim - используется nano :))
Прописываем ваш токен (secret_key) и идентификатор портфеля/аккаунта (account_id):
```
<?php

return [
    'tinkoff_invest' => [
        'secret_key' => '<ВАШ API ТОКЕН>',
        'account_id' => '<ВАШ ИДЕНТИФИКАТОР ПОРТФЕЛЯ>',
    ],
];
```

Как получить токен описано вот здесь: https://tinkoff.github.io/investAPI/token/
А если не знаете идентификатор аккаунта, то можно (После настройки) выполнить консольную команду: 

```
cd /var/www/contest.metaseller.local
php yii tinkoff-invest/accounts
```

и (если все настроено и запущено и токен верен) вы увидите в stdout список идентификаторов ваших портфелей:
```
root@server:/var/www/contest.metaseller.local# php yii tinkoff-invest/accounts

Портфель 1 => 206*******
ИИС => 205*******
Инвесткопилка => 203*******
```

Осталось настроить cron, для этого 
```
sudo su
crontab -e
```
добавляем в crontab строку 
```
* * * * * cd /var/www/contest.metaseller.local && sudo -u www-data php yii app-schedule/run --scheduleFile=@app/config/schedule.php 1>>runtime/logs/scheduler.log 2>&1
```
(В качестве менеджера процессов, запускаемых по расписанию используется библиотека https://github.com/omnilight/yii2-scheduling, в проекте она конфигурируется в файле https://github.com/metaseller/tinkoff-robot-buyer/blob/main/config/schedule.php)

Ну и наконец нам осталось сконфигурировать параметры стратегии нашего робота покупателя (плюс не забыть прописать полученный account_id в файле `config/credentials.php`): 

```
cd /var/www/contest.metaseller.local/config/
vim tinkoff-buy-strategy.php
```

```
<?php

return [
    'ETF' => [
        'TMOS' => [
            'ACTIVE' => true,
            'INCREMENT_VALUE' => 1, // На сколько мы увеличиваем накопленное количество лотов позиций к покупке через каждый период
            'INCREMENT_PERIOD' => 10, // Период в минутах, через который мы инкрементируем количество лотов позиций к покупке
            'BUY_CHECK_PERIOD' => 1, // Период в минутах, через который мы проверяем возможность покупки накопленного количества лотов позиций
            'BUY_LOTS_BOTTOM_LIMIT' => 5, // Не пытаемся совершить покупку, пока не достигнут указанный накопленный лимит лотов к покупке
            'BUY_TRAILING_PERCENTAGE' => 0.09, //Величина в процентах, на которую текущая цена должна превысить трейлинг цену для совершения покупки
        ],
    ],
];
```
семантика этих управляющих параметров указана выше в разделе 'Логика работы'.

Еще, вы имеете возможность изменить appname ваших запросов (см. https://tinkoff.github.io/investAPI/grpc/#appname):

```
cd /var/www/contest.metaseller.local/config/
vim tinkoff-invest.php
```

```
<?php

$credentials = require __DIR__ . '/credentials.php';

return [
    'secret_key' => $credentials['tinkoff_invest']['secret_key'] ?? '',
    'account_id' => $credentials['tinkoff_invest']['account_id'] ?? '',
    'app_name' => 'metaseller.tinkoff-robot-buyer',
];
```

# Дополнительные демонстрационные возможности

Как я писал во введении - этот проект сделан скорее для демонстрации базовых возможностей работы на PHP с API Tinkoff Invest 2. 
Проект представляет собой консольное приложение и вся основная бизнес логика описана в консольном контроллере https://github.com/metaseller/tinkoff-robot-buyer/blob/main/commands/TinkoffInvestController.php

Помимо функционала робота-покупателя ETF вы можете поиграться со следующими консольными командами: 

1) Получение в STDOUT списка идентификаторов ваших портфелей: 
```
cd /var/www/contest.metaseller.local
php yii tinkoff-invest/accounts
```

Команда обслуживается логикой метода `TinkoffInvestController::actionAccounts()` (https://github.com/metaseller/tinkoff-robot-buyer/blob/main/commands/TinkoffInvestController.php#L56)

2) Получение в STDOUT информацию о составе вашего портфеля с указанным идентификатором аккаунта (портфеля):
```
cd /var/www/contest.metaseller.local
php yii tinkoff-invest/portfolio 206*******
```

Команда обслуживается логикой метода `TinkoffInvestController::actionPortfolio(string $account_id)` (https://github.com/metaseller/tinkoff-robot-buyer/blob/main/commands/TinkoffInvestController.php#L103)

3) Получение в STDOUT информацию о суммарном пополнении портфеля с разбивкой по периодам (этот функционал я набросал на коленке, когда возникла задача контроля суммы пополнения ИИС по месяцам (в каком месяце сколько закинул на счет) и суммарного пополнения ИИС по годам):
```
cd /var/www/contest.metaseller.local
php yii tinkoff-invest/funding 206*******
```

4) Демонстрация подписки на стрим стакана и вывод информации со стрима в STDOUT:
```
cd /var/www/contest.metaseller.local
php yii tinkoff-invest/market-data AAPL
```

PS: Обратите внимание, что кучка простых примеров есть и в моем SDK Tinkoff Invest 2 для PHP, 
вы можете, используя composer, подключить к своему проекту на PHP библиотеку https://github.com/metaseller/tinkoff-invest-api-v2-php и поиграться с примерами https://github.com/metaseller/tinkoff-invest-api-v2-php/tree/main/examples
без необходимость использовать Yii2 Framework.

# Полезные ссылки

1) Документация Tinkoff Invest Api для разработчиков доступна по ссылке: https://tinkoff.github.io/investAPI/
2) Коммьюнити разработчиков в Telegram: https://t.me/joinchat/VaW05CDzcSdsPULM
3) Коммьюнити разработчиков по алгоритмической торговле: https://t.me/tradinggroupTinkoff
4) Неофициальный SDK Tinkoff Invest Api v2 для PHP: https://github.com/metaseller/tinkoff-invest-api-v2-php (https://packagist.org/packages/metaseller/tinkoff-invest-api-v2-php)
5) Обертка для Yii2 вокруг неофициального SDK Tinkoff Invest Api v2 для PHP: https://github.com/metaseller/tinkoff-invest-api-v2-yii2 (https://packagist.org/packages/metaseller/tinkoff-invest-api-v2-yii2)
6) Данный демо-проект: https://github.com/metaseller/tinkoff-robot-buyer (https://packagist.org/packages/metaseller/tinkoff-robot-buyer)
7) Коммьюнити по Tinkoff Invest Robot Contest: https://t.me/tinkoff_invest_robot_contest
