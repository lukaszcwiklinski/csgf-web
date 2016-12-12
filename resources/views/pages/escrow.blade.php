@extends('layout')

@section('content')
    <main>
        <div class="content-block">
            <div class="about-page">
                <div class="title-block">
                    <h2>Escrow</h2>
                </div>

                <div class="page-content">

                    <div class="page-main-block">
                        <div class="page-mini-title">Что это такое?</div>
                        <div class="page-block">
                            С 9 декабря 2015 года, все пользователи, которые передают предметы в Стиме, должны использовать мобильный аутентификатор Steam на протяжении как минимум 7 дней.<br>
                            То есть с 9 декабря, для того чтобы совершать обмены в Стиме, вам нужно установить мобильное приложение на ваш телефон и включить защиту и приложение нужно установить уже сегодня, чтобы прошло 7 дней после его установки, чтобы с 9 декабря вы смогли спокойно совершать обмены.<br>
                            Подробная информация есть здесь: <a href="http://store.steampowered.com/mobile" target="_blank">http://store.steampowered.com/mobile</a>
                        </div>
                    </div>

                    <div style="text-align: center;font-size: 16px;line-height: 25px;font-weight: 600;color: #DD8D8D;text-transform: uppercase;margin: 10px 0px 25px;border: 3px dashed;padding: 10px;">
                        Вы не сможете отправлять трейды, если не привяжите свой аккаунт Steam к мобильному телефону.
                        <br>ВАШИ ВЕЩИ БУДУТ ПЕРЕДАВАТЬСЯ 15 ДНЕЙ ЕСЛИ ВЫ НЕ ВКЛЮЧИТЕ ЭТУ ЗАЩИТУ!</div>

                    <div class="page-main-block" style="border-bottom: 1px solid #3D5260; padding-bottom: 20px;">
                        <div class="page-mini-title">Как это будет работать:</div>
                        <div class="page-block">
                            Когда вы будете отправлять трейд нашему боту (или любому другому пользователю Steam) после нажатия кнопки "Предложить обмен" вам придет уведомления в мобильный клиент Steam о подтверждении трейда. Как только подтверждаете трейд - другой пользователь (а в нашем случае наш бот) моментально получает от вас предметы.
                        </div>
                    </div>

                    <div class="page-main-block">
                        <div class="page-mini-title">Подробная инструкция:</div>
                        <div class="page-block">
                            <ul style="list-style-type: decimal;">
                                <li>Скачайте мобильное приложение Steam. Приложение есть только для iOS и Android.<br>
                                    <a href="https://itunes.apple.com/us/app/steam-mobile/id495369748" target="_blank" style="font-size: 16px;color: #6FFF8E;">Скачать приложение на iOS</a> <span style="margin: 0px 20px">/</span>
                                    <a href="https://play.google.com/store/apps/details?id=com.valvesoftware.android.steam.community" target="_blank" style="font-size: 16px;color: #6FFF8E;">Скачать приложение на Android</a><br>
                                    <div style="margin-top: 10px;">Если у вас нет таких устройств, тогда вам нужно будет скачать на ваш компьютер <a href="http://www.bluestacks.com/ru/index.html" target="_blank">Android эмулятор</a> (об этом в самом конце есть инструкция)</div>
                                </li>
                                <li>Запустив приложение, введите свой логи и пароль, введите проверочный код, который придет вам на почту.</li>
                                <li>Перейдите во вкладку <b>Steam Guard</b><br>
                                    <img src="assets/img/escrow/pic1.jpg" style="margin: 20px 0px;"></li>

                                <li>Перейдите в настройки и смените тип авторизации на <b>"Получать коды Steam Guard на телефон"</b><br>
                                    <img src="assets/img/escrow/pic01.png" style="margin: 20px 0px;"></li>

                                <li>Нажмите <b>"далее"</b> и впишите свой номер телефона, если впервые привязываете его к аккаунту стима.<br>
                                    <img src="assets/img/escrow/pic02.png" style="margin: 20px 0px;"></li>

                                <li>В следующем окне Вам нужно будет ввести код, который будет отправлен вам по СМС<br>
                                    <img src="assets/img/escrow/pic03.jpg" style="margin: 20px 0px;"></li>

                                <div style="">Привязка вашего аккаунта Steam к мобильному телефону завершена. Теперь все отправки трейдов вы должны будете подтверждать через данное приложение Steam во вкладке <b>"Подтверждение"</b></div>

                                <img src="assets/img/escrow/1-2-3.png" style="margin: 20px 0px;">

                                <div style="">А также теперь каждый раз когда вы будете в Steam на вашем компьютере, вы должны будете вводить эти одноразовые коды, которые генерируется в приложении во вкладке <b>"Steam Guard"</b></div>

                                <img src="assets/img/escrow/uniqcode.jpg" style="margin: 20px 0px;">


                            </ul>
                        </div>
                    </div>

                    <div class="page-main-block" style="border-top: 3px solid; padding-top: 30px; margin-top: 40px;">
                        <div class="page-mini-title">Android эмулятор или "что делать если у меня нет iOS или Android устройств"</div>
                        <div class="page-block">
                            Сделайте все то, что показано ниже на картинке<br> и после установки приложения Steam на эмулятор возвращайтесь к "Подробной инструкции", которая расписана выше <img src="assets/img/escrow/arrow-up.png" style="width: 40px;"><br>

                            <center style="margin: 15px 0px 5px; color: #fff; font-size: 18px;">Инструкция по установки Android эмулятора BlueStacks<br>
                                <a href="http://www.bluestacks.com/ru/index.html" target="_blank" style="font-size: 16px;color: #6FFF8E;">Скачать Android эмулятор BlueStacks</a></center>
                            <center><img src="assets/img/escrow/dlp.png" style="margin: 20px 0px;"></center>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </main>
    <!-- Chat -->

    <div id="chatHeader" style="display: none;">Чат</div>

    <div id="chatContainer" class="chat-with-prompt" style="display: none;box-shadow: 0 0 10px #1E2127;">
        <span id="chatClose" class="chat-close"></span>
        <div id="chatHeader">Чат</div>
            <div class="chat-prompt" id="chat-prompt">
                <div class="chat-prompt-top">Чат сайта:</div>
            </div>
        <div id="chatScroll">
            <div id="messages">
            </div>
        </div>
        @if(!Auth::guest())
        <form action="#" class="chat-form">
            <textarea id="chatInput" maxlength="255" placeholder="Введите сообщение"></textarea>
            <div class="chat-actions">
                <a id="chatRules" class="chat-rules">Правила чата</a>
                
                <div class="smiles">
                    <div class="sub">
                        <?php for($i = 1; $i<= 505; $i++)echo "<img id=\"smile\" class=\"smile-smile-_".$i."_\" onclick=\"add_smile(':sm".$i.":')\">"; ?>
                    </div>
                    <span></span>
                </div>
                
            </div>
            <button class="chat-submit-btn">Отправить</button>
        </form>
        @else
        <a id="notLoggedIn" href="{{ route('login') }}">Войти через Steam</a>
        @endif

        <div style="display: none;">
            <div class="box-modal affiliate-program" id="chatRulesModal">
                <div class="box-modal-head">
                    <div class="box-modal_close arcticmodal-close"></div>
                </div>
                <div class="box-modal-content">
                    <div class="content-block">
                        <div class="title-block">
                            <h2>Правила чата</h2>
                        </div>
                    </div>
                    <div class="text-block-wrap">
                        <div class="text-block">
                            <div class="page-main-block" style="text-align: left !important;">
                                <div class="page-block">За чатом на сайте 24 часа в сутки, 7 дней в неделю, следит модератор, который банит пользователей в чате за нарушения правил</div>

                                <div class="page-mini-title">В чате запрещается:</div>
                                <div class="page-block">
                                    <ul>
                                        <li style="margin-bottom: 5px;">Спам; Спам своим рефералом.</li>
                                        <li style="margin-bottom: 5px;">Оскорблять других участников;</li>
                                        <li style="margin-bottom: 5px;">Оставлять ссылки на сторонние ресурсы;</li>
                                        <li style="margin-bottom: 5px;">Выпрашивать скины у других участников;</li>
                                        <li style="margin-bottom: 5px;">Писать слово подкрутка или обвинять админов в подкрутке;</li>
                                        <li style="margin-bottom: 0px;">Оставлять сообщения о предложении покупки, продажи или обмена скинов.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Chat END -->
@endsection