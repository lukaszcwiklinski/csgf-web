@extends('layout')

@section('content')
    <link rel="stylesheet" href="{{ $asset('assets/css/shop.css') }}"/>
    <script src="{{ $asset('assets/js/deposit.js') }}"></script>

    <div class="title-block">
        <h2>
            Депозит
        </h2>
    </div>
    <div class="page-content">
        <div class="page-main-block" style="border-bottom: 1px solid #3D5260; width:60%; display: inline-block;padding-top: 20px;border-right: 1px solid #3D5260;margin-bottom:0px">
            <div class="page-mini-title">Важная информация при депозите:</div>
            <div class="page-block">
                <ul>
                    <li>Предметы стоимостью <b>менее 10р</b> оцениваются в <b>2 раза</b> дешевле.</li>
                    <li>Предметы стоимостью <b>менее {{ config("comission_on_<") }}р</b> оцениваются дешевле на <b>{{ config('comission_on_%') }}%</b>.</li>
                    <li>Цену предмета вы видите сразу при выборе.</li>
                    <li>После пополнения ваш лимит на сутки будет увеличен на сумму пополнения.</li>
                </ul>
            </div>
        </div>
        <div class="page-main-block" style="border-bottom: 1px solid #3D5260; width:39%; display: inline-block;padding-top: 20px;padding-left:15px;margin-bottom:0px">
            <div class="page-mini-title">Как пополнять:</div>
            <div class="page-block">
                <ul>
                    <li>Вы нажимаете "Обновить инвентарь".</li>
                    <li>Выбираете предметы для депозита.</li>
                    <li>"Внести предметы" и принимаете.</li>
                    <li>Нажимаете "проверить обмены".</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="buy-cards-container" style="padding-top: 10px;">
        <div style="overflow: hidden;">
            <div id="S_dep" style="">
                <div class="buy-cards-block" style="margin-top: 0px;">
                    <div class="shop-item-filters">
                        <div class="left-totalitems" style="margin-top: 3px;">
                           Ваши выбранные предметы | Предметов: <span id="dcart-total">0</span> | Сумма: <span id="dcart-total-price">0</span>
                        </div>
                        <a onclick="getitems()" class="myhistorylink" style="margin-top: 3px;">Внести предметы</a>
                        <a target="_blank" id="acceptTradeUrl" class="myhistorylink" href="/" style="display:none; margin-top: 3px;">Принять обмен</a>
                        <a onclick="checkOffers()" class="myhistorylink" style="margin-top: 3px;">Проверить Обмены</a>
                    </div>
                </div>
                <div id="dep-cart-list" class="cart-list" style="margin-right: -25px;display: block;">
                    <script type="text/template" id="item-template">
                        <div class="deposit-item <%= className %> up-<%= className %>" id="deposit-item_<%= id %>" onclick="buy( <%= id %> )">
                            <div class="deposit-item-wrap">
                                <div class="img-wrap"><img src="<%= image %>" alt="" title=""/></div>
                                <div class="name"><%= name %></div>
                                <div class="deposit-price"><%= priceText %> <span>руб</span></div>
                                <div class="deposit-exterior"><%= shortexterior %></div>
                                <div class="deposit-count">x<%= count %></div>
                            </div>
                        </div>
                    </script></div>
                @if(!$u->trade_link)
                    <div class="green-txt-info">Нет ссылки для обмена!</div>
                @endif
                <div class="buy-cards-block" style="margin-top: 0px;">
                    <div class="shop-item-filters">
                        <div class="left-totalitems" style="margin-top: 3px;">
                           Ваш инвентарь
                        </div>
                        <a href="{{ route('cards-history') }}" style="margin: 3px 5px 0px 12px;" class="myhistorylink">История обменов</a>
                        <a onclick="inv_update()" style="margin: 3px 5px 0px 12px;" class="myhistorylink">Обновить инвентарь</a>
                    </div>
                </div>

                <div id="dep-items-list" class="items-list" style="margin-right: -25px; display: block;"></div>
            </div>
        </div>
    </div>
    <div style="display: none;">
        <div class="box-modal affiliate-program" id="shopDepositModal">
            <div class="box-modal-head">
                <div class="box-modal_close arcticmodal-close"></div>
            </div>
            <div class="box-modal-content">
                <div class="content-block">
                    <div class="title-block">
                        <h2>Перевод в карточки</h2>
                    </div>
                </div>
                <div class="b-modal-cards" style="border: none; width: 609px; border-radius: 0px;" id="cardDepositModal">
                    <div class="box-modal-container">
                        <div class="box-modal-content">
                            <div class="add-balance-block">
                                <div class="balance-item">
                                    Ваш баланс:
                                    <span class="userBalance">{{ $u->money }} </span> <div class="price-currency">рублей</div>
                                </div>

                                <span class="icon-arrow-right"></span>
                                <div id="GDonate" class="input-group">
                                    <form method="GET" action="/pay">
                                        <input type="hidden" name="user_id" value="2542">
                                        <input type="text" name="sum" placeholder="Введите сумму">
                                        <button type="submit" class="btn-add-balance" name="">пополнить</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
                        <?php for($i = 1; $i< 514; $i++)echo "<img src=\"/assets/img/smiles/smile (".$i.").png\" id=\"smile\" style=\"background:none;\" onclick=\"add_smile(':sm".$i.":')\">"; ?>
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
    <script>
        function updateBalance() {
            $.post('/getBalance', function (data) {
                $('.userBalance').text(data);
            });
        }
    </script>
@endsection
