<?php

namespace App\Http\Controllers;

use App\Bet;
use App\Game;
use App\Item;
use App\Services\SteamItem;
use App\Ticket;
use App\User;
use App\Shop;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\WinnerTicket;
use Illuminate\Support\Str;
use Illuminate\Support\Cache;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use LRedis;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Storage;

class GameController extends Controller
{
	const URL_REQUEST = 'http://backpack.tf/api/IGetMarketPrices/v1/?key=';
	
	const SENDSHOP_ON = 1;										# 0 - выключить откравку комиссии магазину, 1 - включить отправку.
	const SHOPSTEAMID64 = 76561198073063637;					# SteamID64 От аккаута магазина. (он должен быть авторизирован на сайте)!
	const BOTSTEAMID64 = 76561198067721846;						# SteamID64 От аккаута магазина. (он должен быть авторизирован на сайте)!    
    
    const BONUS_ON = 1;											# 0 - выключить бонус бота, 1 - включить.
	const BONUS_ID = 76561197960265728;							# SteamID64 бонус бота
	
	const LOWCHANSE = 6; 										# Шанс выпадения лоу кф (↓число - ↑шанс) [0, беск]
    
    const PayType = 1;											# 0 - GDonate, 1 - FreeKassa
 
	const GDonateKeyPublic = '18810-672';
    const GDonateKey = 'abd201ef3ddd051d6af8a2812f71eb2b';
	
	const FreeKassaID = '28279';
    const FreeKassaSecret1 = 'twj6ecdi';
	const FreeKassaSecret2 = 'vocivn29';
	
    const SEND_OFFERS_LIST = 'send.offers.list';
    const NEW_BET_CHANNEL = 'newDeposit';
    const BET_DECLINE_CHANNEL = 'depositDecline';
    const INFO_CHANNEL = 'msgChannel';
    const SHOW_WINNERS = 'show.winners';
    const LOG_CHANNEL = 'app_log';

    public $game;

    protected $lastTicket = 0;

    private static $chances_cache = [];
    
	public function __construct()
    {
        $this->redis = LRedis::connection();
        parent::__construct();
        $this->game = $this->getLastGame();
        $this->lastTicket = $this->redis->get('last.ticket.' . $this->game->id);
        if(is_null($this->lastTicket)) $this->lastTicket = 0;
    }

    public function  __destruct(){
        $this->redis->disconnect();
    }
    public function deposit()
    {
        return redirect(config('mod_game.bot_trade_link'));
    }
	public function curl($url) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
		curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 

		$data = curl_exec($ch);
		curl_close($ch);

		return $data;
	}
    
    public function get_real_price($price, $mhn){
		if ($price < 5){
            $tprice = self::curl('http://steamcommunity.com/market/priceoverview/?currency=5&country=ru&appid='.config('mod_game.appid').'&market_hash_name=' . urlencode($mhn) . '&format=json');
            $tprice = json_decode($tprice);
            if (isset($tprice->success)){
                $lowest = floatval(str_ireplace(array(','),'.',str_ireplace(array('pуб.'),'',$tprice->lowest_price)));
                $median = floatval(str_ireplace(array(','),'.',str_ireplace(array('pуб.'),'',$tprice->median_price)));
                if($lowest<$median) $price = $lowest; else $price = $median;
            }
		}
		return $price;
	}
    private function _parseItems(&$items, &$missing = false, &$price = false)
    {
        $itemInfo = [];
        $total_price = 0;
        $i = 0;

        foreach ($items as $item) {
            $value = $item['classid'];
            if ($item['appid'] != config('mod_game.appid')) {
                $missing = true;
                break;
            }
            $dbItemInfo = Item::where('market_hash_name', $item['market_hash_name'])->first();
            if (is_null($dbItemInfo)) {
                if (!isset($itemInfo[$value])) $itemInfo[$value] = new SteamItem($item);
                if (!$itemInfo[$value]->price){
                    $price = true;
                    break;
                } else {
                    $itemInfo[$value]->price = self::get_real_price($itemInfo[$value]->price, $item['market_hash_name']);
                    $dbItemInfo = Item::create((array)$itemInfo[$item['classid']]);
                }
            } else {
                if ($dbItemInfo->updated_at->getTimestamp() < Carbon::now()->subHours(24)->getTimestamp()) {
                    $si = new SteamItem($item);
                    if ($si->price){
						$dbItemInfo->price = self::get_real_price($si->price, $item['market_hash_name']);
						$dbItemInfo->save();
					}
                }
            }
            $itemInfo[$value] = $dbItemInfo;
            if ($itemInfo[$value]->price <= 0) $itemInfo[$value]->price = 0.1;          //Если цена меньше единицы, ставим единицу
            $total_price = $total_price + $itemInfo[$value]->price;
            $items[$i]['price'] = $itemInfo[$value]->price;
            unset($items[$i]['appid']);
            $i++;
        }
        return $total_price;
    }
    /*public function updatePrices(){
        $items = \DB::table('items')->where('price','<',1)->get();
        foreach ($items as $item){
            if($item->price<1){
                $price = self::curl('http://steamcommunity.com/market/priceoverview/?currency=5&country=ru&appid='.config('mod_game.appid').'&market_hash_name=' . urlencode($item->market_hash_name) . '&format=json');
                $price = json_decode($price);
                if (isset($price->success)){
                    $lowest = floatval(str_ireplace(array(','),'.',str_ireplace(array('pуб.'),'',$price->lowest_price)));
                    $median = floatval(str_ireplace(array(','),'.',str_ireplace(array('pуб.'),'',$price->median_price)));
                    if($lowest<$median) $price = $lowest; else $price = $median;
                    echo $price.' '. $item->market_hash_name .'<br>';
                    \DB::table('items')->where('market_hash_name', $item->market_hash_name)->update(['price' => $price]);
                }
            }
            sleep(1);
        }
        return;
    }*/
    public function getPriceItems()
    {
        $data = file_get_contents(self::URL_REQUEST . config('mod_game.backpack_key') . '&compress=1&appid=' . config('mod_game.appid'));
        $response = json_decode($data);
        $success = $response->response->success;
        if ($success != 0) {
            if(isset($response->response->items)){
                Storage::disk('local')->put('items.txt', $data);
                /*$usd = SteamItem::getActualCurs();
                try{
                    $items = $response->response->items;
                    foreach ($items as $key => $i){
                        $item = $items->$key->value;
                        $price_item = $item / 100 * $usd;
                        $dbItemInfo = Item::where('market_hash_name', $key)->first();
                        if (!is_null($dbItemInfo)) {
                            $dbItemInfo->price = self::get_real_price($price_item);
                            $dbItemInfo->save();
                        }
                    }
                }catch(Exception $e){
                    return false;
                }*/
            }
            return 'Successfully Parsing';
        } else {
            $message = $response->response->message;
            return $message;
        }
    }
    public function currentGame()
    {
		$urls = [
			'csgf',
			'рулетки%20кс%20го%20от%201%20рубля',
			'рулетка%20для%20бомжей',
			'рулетка%20кс%20го%20для%20бомжей',
			'кс%20го%20рулетка%20для%20бомжей',
			'рулетка%20кс%20го%20бонус%20бот',
			'csgf.ru',
			'рулетка%20cs%20go%20для%20бомжей%20и%20новичков%20с%20минимальной%20ставкой%201%20рубль',
			'рулетка%20кс%20го%20от%201%20рубля',
			'csgf.',
			'ксгф',
			'кс%20го%20рулетки%20для%20бомжей',
			'рулетки%20для%20новичков%20кс%20го%20минимальная%20ставка%201%20рубль'
		];
        $game = Game::orderBy('id', 'desc')->first();
        $bets = $game->bets()->with(['user', 'game'])->get()->sortByDesc('created_at');
        $user_chance = $this->_getUserChanceOfGame($this->user, $game);
        $chances = json_encode($this->_getChancesOfGame($game));
        if (!is_null($this->user)) $user_items = $this->user->itemsCountByGame($game);
		$html = '';
		$rand = rand(0, (count($urls) - 1));
		$url = 'https://yandex.ru/search/?text='.$urls[$rand];
		parent::setTitle(round($game->price) . ' р. | ');
        return view('pages.index', compact('game', 'bets', 'user_chance', 'chances', 'user_items', 'url'));
    }
    public function getLastGame()
    {
        $game = Game::orderBy('id', 'desc')->first();
        if (is_null($game)) $game = $this->newGame();
        return $game;
    }
    public function getCurrentGame()
    {
        $this->game->winner;
        return $this->game;
    }
    public function dec()
    {
        $sumtd = \DB::table('games')->where('status', Game::STATUS_FINISHED)->where('created_at', '>=', Carbon::today())->sum('price');
		$sumfw = \DB::table('games')->where('status', Game::STATUS_FINISHED)->where('created_at', '>=', Carbon::today()->subWeek())->sum('price');
		$sumfr = \DB::table('games')->where('status', Game::STATUS_FINISHED)->sum('price');
		$echo = 'Разыграно сегодня: ' . $sumtd . ' | Комиссия: ~' . ($sumtd * 0.09) . '<br> Разыграно за неделю: ' . $sumfw . ' | Комиссия: ~' . ($sumfw * 0.09) . '<br> Разыграно всего: ' . $sumfr . ' | Комиссия: ~' . ($sumfr * 0.09);
        return $echo;
    }
	public static function _fixGame($id)
	{
        $game = Game::where('id', $id)->first();
		$bonus = User::where('steamid64', self::BONUS_ID)->first();
		$bets = Bet::where('game_id', $id)->where('user_id', '!=', $bonus->id)->orderBy('id')->get();
		$lastTicket = 0;
		foreach ($bets as $bet){
			$B = Bet::where('id', $bet->id)->first();
			$B->from = $lastTicket + 1;
			$B->to = $B->from + ($B->price * 100) - 1;
			$B->save();
			$lastTicket = $B->to;
		}
        $game = Game::where('id', $id)->first();
        $game->price = $lastTicket/100;
        $chance = 0;
        if (!is_null($game->winner)) {
			if ($game->winner->steamid64 != self::BONUS_ID) {
				$bet = Bet::where('game_id', $game->id)
					->where('user_id', $game->winner->id)
					->sum('price');
				if ($game->price > 0 && $bet) $chance = round($bet / $game->price, 3) * 100; 
			} else {
				$chance = 0;
			}
        }
        
		$game->chance = $chance;
        $game->save();
	}
    public function getWinners()
    {
        $us = $this->game->users();
		self::_fixGame($this->game->id);
        $lastBet = Bet::where('game_id', $this->game->id)->orderBy('to', 'desc')->first();
        $winTicket = WinnerTicket::where('game_id', $this->game->id)->first();
        if($winTicket == null) {
            $winTicket = ceil($this->game->rand_number * $lastBet->to);
        } else {
            $winTicket = $winTicket->winnerticket;
            $this->game->rand_number = ($winTicket - 1)/$lastBet->to;
			
            if(strlen($this->game->rand_number)<19) {
                $diff = 19 - strlen($this->game->rand_number);
                $min = "1";
                $max = "9";
                for($i = 1; $i < $diff; $i++) {
                    $min .= "0";
                    $max .= "9";
                }
                $this->game->rand_number = $this->game->rand_number."".  rand($min, $max);
            }
			\DB::table('winner_tickets')->truncate();
        }
		$winningBet = Bet::where('game_id', $this->game->id)->where('from', '<=', $winTicket)->where('to', '>=', $winTicket)->first();
        $this->game->winner_id      = $winningBet->user_id;
		$this->game->price 			= $lastBet->to/100;
        $this->game->status         = Game::STATUS_FINISHED;
        $this->game->finished_at    = Carbon::now();
		$chance = $this->_getUserChanceOfGame($this->game->winner, $this->game);
		$items = $this->sendItems($this->game->bets, $this->game->winner, $chance);
        $this->game->won_items      = json_encode($items['itemsInfo']);
		$this->game->comission      = json_encode($items['commissionItems']);
		$this->game->chance         = $chance;
        $this->game->save();
		
		$users = [];
		foreach ($us as $usr) {
			for($i = 1; $i < round($this->_getUserChanceOfGame($usr, $this->game)); $i++) {
				$users[] = $usr;
			}
		}
        $returnValue = [
            'game'   => $this->game,
            'winner' => $this->game->winner,
            'round_number' => $this->game->rand_number,
            'ticket' => $winTicket,
            'tickets' => $lastBet->to,
            'users' => $us,
			'userchanses' => $users,
            'chance' => $chance
        ];

        return response()->json($returnValue);
    }
	public function lw(){
		$lastgame = \DB::table('games')->where('id', \DB::table('games')->max('id'))->first();
		if (!is_null($lastgame)){
			if ($lastgame->status == Game::STATUS_FINISHED) {
				$user = User::where('id', $lastgame->winner_id)->first();
                unset($user->password);
				$lw = [
					'user' => $user,
					'price' => $lastgame->price,
					'chance' => self::_getUserChanceOfGame($user, $lastgame)
				];
			} else {
				$lastgame = \DB::table('games')->where('id', (\DB::table('games')->max('id')) - 1)->first();
				$user = User::where('id', $lastgame->winner_id)->first();
                unset($user->password);
				$lw = [
					'user' => $user,
					'price' => $lastgame->price,
					'chance' => self::_getUserChanceOfGame($user, $lastgame)
				];
			}	
		} else {
			$u = [
				'avatar' => '/assets/img/blank.jpg',
				'username' => 'Пока не выбран',
				'steamid64' => ''
			];
			$lw = [
				'user' => $u,
				'price' => '???',
				'chance' => '???'
			];
		}
		return $lw;
	}	
	public function mlfv(){
		$u = ['avatar' => '/assets/img/blank.jpg','username' => 'Пока не выбран','steamid64' => ''];
		$mlfv = ['user' => $u,'price' => '???','chance' => '???'];
		$mlfgame = \DB::table('games')->where('status', Game::STATUS_FINISHED)->where('created_at', '>=', Carbon::today()->subWeek())->min('chance');
		$mlfgame = \DB::table('games')->where('chance', $mlfgame)->orderBy('price', 'desc')->first();
		if (!is_null($mlfgame)){
			$u = User::where('id', $mlfgame->winner_id)->first();
            unset($u->password);
			$mlfv = ['user' => $u,'price' => $mlfgame->price,'chance' => self::_getUserChanceOfGame($u, $mlfgame)];
		}
		return $mlfv;
	}
	public function mltd(){
		$u = ['avatar' => '/assets/img/blank.jpg','username' => 'Пока не выбран','steamid64' => ''];
		$mltd = ['user' => $u,'price' => '???','chance' => '???'];
		$mltdgame = \DB::table('games')->where('status', Game::STATUS_FINISHED)->where('created_at', '>=', Carbon::today())->min('chance');
		$mltdgame = \DB::table('games')->where('chance', $mltdgame)->orderBy('price', 'desc')->first();
		if (!is_null($mltdgame)){
			$u = User::where('id', $mltdgame->winner_id)->first();
            unset($u->password);
			$mltd = ['user' => $u,'price' => $mltdgame->price,'chance' => self::_getUserChanceOfGame($u, $mltdgame)];
		}
		return $mltd;
	}
    
	public function update(){
		$response = [
			'total' => \App\Game::gamesToday(),
			'max' => round(\App\Game::sumFAT()),
			'today' => \App\Game::usersToday(),
			'last' => \App\Game::lastGame(),
			'lw' => $this->lw(),
			'mltd' => $this->mltd(),
			'mlfv' => $this->mlfv()
		];
		
		$value = (object)$response;
        return response()->json($value);
    }
	
    public function sendItems($bets, $user, $chance){						
        $itemsInfo = [];
		$itemsInfor = [];
        $items = [];
        $commission = config('mod_game.comission');
        $commissionItems = [];
		$nextbetItems = [];
        $returnItems = [];
        $tempPrice = 0;
		$bonus = User::where('steamid64', self::BONUS_ID)->first();
		$firstBet = Bet::where('game_id', $this->game->id)->where('user_id', '!=' , $bonus->id)->orderBy('created_at', 'asc')->first();
		if(!is_null($firstBet)){
			if ($firstBet->user == $user) $commission = $commission - config('mod_game.comission_first_bet');
		}
		$name = strtolower($user->username);
		if (strpos(strtolower(' '.$name),  strtolower(str_replace("/", "", str_replace("://", "", str_replace("http", "", str_replace("https", "", config('app.url'))))))) != false) $commission = $commission - config('mod_game.comission_site_nick');
        $commissionPrice = round(($this->game->price / 100) * $commission);
        foreach ($bets as $bet) {
            $betItems = json_decode($bet->items, true);
            foreach ($betItems as $item) {
                if (($bet->user_id == $user->id) && ($chance >= config('mod_game.comission_minchance'))) {
                    $itemsInfo[] = $item;
                    if (isset($item['classid'])) {
                        $returnItems[] = $item['classid'];
                    } else {
                        $user->money = $user->money + $item['price'];
                    }
                } else {
                    $items[] = $item;
                }
            }
        } 
		uasort($items,function($f1,$f2){
			if($f1['price'] < $f2['price']) return 1;
			elseif($f1['price'] > $f2['price']) return -1;
			else return 0;
		});
		foreach ($items as $item) {
			if ($item['price'] == 0) $item['price'] = 0.1;
			if ((($item['price'] + $tempPrice) <= $commissionPrice)) {
				$commissionItems[] = $item;
				$tempPrice = $tempPrice + $item['price'];
			} else {
				$itemsInfo[] = $item;
				if (isset($item['classid'])) {
					$returnItems[] = $item['classid'];
				} else {
					$user->money = $user->money + $item['price'];
				}
			}
		}		
		$user->save();
        $this->redis->publish(self::LOG_CHANNEL, json_encode('Победил: '. $user->username . ' | Шанс на победу: '.$chance . ' | Комиссия: '.$tempPrice));
		$value = [
			'appId' => config('mod_game.appid'),
			'steamid' => $user->steamid64,
			'accessToken' => $user->accessToken,
			'items' => $returnItems,
			'game' => $this->game->id
		];
		$this->redis->rpush(self::SEND_OFFERS_LIST, json_encode($value));
		if (self::BONUS_ON == 1) {
			$bonusdrop = \DB::table('bonus_items')->first();
			if(is_null($bonusdrop)){
				$bonusItemsPrice =  round(($this->game->price / 100),2);
				if ($bonusItemsPrice > 5) $bonusItemsPrice = 5.00;
				if ($bonusItemsPrice < 0.01) $bonusItemsPrice = 0.01;
				$bonusitems = [];
				$bonusitem = [
					'id' => $bonusItemsPrice,
					'img' => '/assets/img/card.png',
					'price' => $bonusItemsPrice,
					'name' => 'Карточка на ' . $bonusItemsPrice . ' руб.',
					'style' => '-webkit-filter: hue-rotate(' . $bonusItemsPrice * 10 . 'deg)'
				];
			} else {
				$bonusitem = json_decode($bonusdrop->item, true);
				$bonusItemsPrice = $bonusdrop->price;
				\DB::table('bonus_items')->where('id', $bonusdrop->id)->delete();
			}
			$bonusitems[] = $bonusitem;
			$bonus = User::where('steamid64', self::BONUS_ID)->first();
			$returnValue = [
				'offerid' => 0,
				'userid' => $bonus->id,
				'message' => '',
				'steamid64' => $bonus->steamid64,
				'gameid' => $this->game->id,
				'items' => $bonusitems,
				'price' => $bonusItemsPrice,
				'success' => true
			];
			$this->redis->lpush('bets.list', json_encode($returnValue)); 
		}
		if (self::SENDSHOP_ON == 1) {
			$shopItems = [];
			foreach ($commissionItems as $item) {
				if (isset($item['classid'])) {
					$shopItems[] = $item['classid'];
				}
			}
			$shop = User::where('steamid64', self::SHOPSTEAMID64)->first();
			if ($shop != NULL) {
				$valueShop = [
					'appId' => config('mod_game.appid'),
					'steamid' => $shop->steamid64,
					'accessToken' => $shop->accessToken,
					'items' => $shopItems,
					'game' => $this->game->id
				];
				$this->redis->rpush(self::SEND_OFFERS_LIST, json_encode($valueShop));
			}
		}
		$response = [
			'itemsInfo' => $itemsInfo,
			'commissionItems' => $commissionItems
		];
        return $response;
    }
	
    public function newGame(){
		\Cache::put('new_game', 'new_game', 5);
        
        $rand = \DB::table('winner_rands')->where('game_id', $this->game->id + 1)->first();
        if(is_null($rand)) {
			$rand_number = "0.";
			$firstrand = mt_rand(20, 80);
			if (mt_rand(0, self::LOWCHANSE) == 0) $firstrand = mt_rand(3, 96);
			if (mt_rand(0, (self::LOWCHANSE * 2)) == 0) $firstrand = mt_rand(0, 9) . mt_rand(0, 9);
			if(strlen($firstrand) < 2) $firstrand = "0" . $firstrand;
			$rand_number .= $firstrand;
			for($i = 1; $i < 15; $i++) {
				$rand_number .= mt_rand(0, 9);
			}
			$rand_number .= mt_rand(1, 9);
        } else {
			$rand = $rand->randn;
            if(strlen($rand)<19) {
                $diff = 19 - strlen($rand);
                $min = "1";
                $max = "9";
                for($i = 1; $i < $diff; $i++) {
                    $min .= "0";
                    $max .= "9";
                }
                $rand = $rand . "" . rand($min, $max);
            }
			$rand_number = $rand;
			\DB::table('winner_rands')->truncate();
        }
		
        $game = Game::create(['rand_number' => $rand_number]);
        $game->hash = md5($game->rand_number);
        $game->rand_number = 0;
        $this->redis->set('current.game', $game->id);
		$this->redis->set('last.ticket.' . $this->game->id, 0);
        return $game;
    }
    public static function object_to_array($data){
        if (is_array($data) || is_object($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $result[$key] = self::object_to_array($value);
            }
            return $result;
        }
        return $data;
    }
    public function checkOffer(){
        $data = $this->redis->lrange('check.list', 0, -1);
        foreach ($data as $offerJson) {
            $offer = json_decode($offerJson);
            $accountID = $offer->accountid;
            $items = json_decode($offer->items, true);
            $itemsCount = count($items);

            $user = User::where('steamid64', $accountID)->first();
            if (is_null($user)) {
                $this->redis->lrem('usersQueue.list', 1, $accountID);
                $this->redis->lrem('check.list', 0, $offerJson);
                $this->redis->rpush('decline.list', $offer->offerid);
                continue;
            } else {
                if (empty($user->accessToken)) {
                    $this->redis->lrem('usersQueue.list', 1, $accountID);
                    $this->redis->lrem('check.list', 0, $offerJson);
                    $this->redis->rpush('decline.list', $offer->offerid);
                    $this->_responseErrorToSite('Введите трейд ссылку!', $accountID, self::BET_DECLINE_CHANNEL);
                    continue;
                }
            }
			$words = mb_strtolower(file_get_contents(dirname(__FILE__) . '/words.json'));
			$words = self::object_to_array(json_decode($words));
			if (!isset($offer->message)){
				$offer->message = '';
			} else {
				$message = mb_strtolower($offer->message);
				foreach ($words as $key => $value) {
					$message = str_ireplace($key, $value, $message);
				}
				$offer->message = $message;
			}
			if($offer->message != 'bonus'){
				$totalItems = $user->itemsCountByGame($this->game);
				if (($itemsCount + $totalItems) > config('mod_game.max_items')) {
					$this->_responseErrorToSite('Максимальное кол-во предметов для - ' . config('mod_game.max_items') . '; ' . ($itemsCount + $totalItems - config('mod_game.max_items')) . ' предметов уйдет на следущую игру', $accountID, self::BET_DECLINE_CHANNEL);
					/*$this->redis->lrem('usersQueue.list', 1, $accountID);
					$this->redis->lrem('check.list', 0, $offerJson);
					$this->redis->rpush('decline.list', $offer->offerid);
					continue;*/
                    
				}
			}
			$total_price = $this->_parseItems($items, $missing, $price);
			if($offer->message != 'bonus'){
				if ($missing) {
					$this->_responseErrorToSite('Принимаются только предметы из CS:GO', $accountID, self::BET_DECLINE_CHANNEL);
					$this->redis->lrem('usersQueue.list', 1, $accountID);
					$this->redis->lrem('check.list', 0, $offerJson);
					$this->redis->rpush('decline.list', $offer->offerid);
					continue;
				}

				if ($price) {
					$this->_responseErrorToSite('В вашем трейде есть предметы, цены которых мы не смогли определить', $accountID, self::BET_DECLINE_CHANNEL);
					$this->redis->lrem('usersQueue.list', 1, $accountID);
					$this->redis->lrem('check.list', 0, $offerJson);
					$this->redis->rpush('decline.list', $offer->offerid);
					continue;
				}
				if ($total_price < config('mod_game.min_price')) {
					$this->_responseErrorToSite('Минимальная сумма депозита ' . config('mod_game.min_price') . 'р.', $accountID, self::BET_DECLINE_CHANNEL);
					$this->redis->lrem('usersQueue.list', 1, $accountID);
					$this->redis->lrem('check.list', 0, $offerJson);
					$this->redis->rpush('decline.list', $offer->offerid);
					continue;
				}
			}
            $returnValue = [
                'offerid' => $offer->offerid,
				'message' => $offer->message,
                'userid' => $user->id,
                'steamid64' => $user->steamid64,
                'gameid' => $this->game->id,
                'items' => $items,
                'price' => $total_price,
                'success' => true
            ];
			$this->_responseMessageToSite('Обмен обработан - принимаем.', $user->steamid64);
            $this->redis->rpush('checked.list', json_encode($returnValue));
            $this->redis->lrem('check.list', 0, $offerJson);
        }
		
        return response()->json(['success' => true]);
    }
    public function newBet(){
		if (\Cache::has('new_game')) return $this->_responseSuccess();
        $data = $this->redis->lrange('bets.list', 0, -1);
		$bonus = User::where('steamid64', self::BONUS_ID)->first();
		if ($bonus == NULL) \DB::table('users')->insertGetId([
			'username' => 'BONUS',
			'avatar' => 'http://www.csgofear.ru/assets/img/gift.png',
			'steamid' => 'STEAM_0:1:00000000',
			'steamid64' => '76561197960265728',
			'trade_link' =>  'https://steamcommunity.com/tradeoffer/new/?partner=112797909&token=R06NjbU6',
			'accessToken' => 'R06NjbU6'
		]); 
        foreach ($data as $newBetJson) {
            $newBet = json_decode($newBetJson, true);
            $user = User::find($newBet['userid']);
			$this->game = $this->getLastGame();
			if (is_null($user)) continue;
			if ($user->ban == 0){
				if ($this->game->id < $newBet['gameid']) continue;
				if ($this->game->id >= $newBet['gameid']) $newBet['gameid'] = $this->game->id;

				if ($this->game->status == Game::STATUS_PRE_FINISH || $this->game->status == Game::STATUS_FINISHED) {
					$this->_responseMessageToSite('Ваша ставка пойдёт на следующую игру.', $user->steamid64);
					$this->redis->lrem('bets.list', 0, $newBetJson);
					$newBet['gameid'] = $newBet['gameid'] + 1;
					$this->redis->rpush('bets.list', json_encode($newBet));
					continue;
				}
				if($newBet['message'] == 'bonus'){
					foreach ($newBet['items'] as $item){
						$id = \DB::table('bonus_items')->insertGetId(['item' => json_encode($item),'price' => $item['price']]);
					}
					$this->redis->lrem('bets.list', 0, $newBetJson);
					continue;
				}
				$this->redis->lrem('bets.list', 0, $newBetJson);
				$totalItems = $user->itemsCountByGame($this->game);
				if (($totalItems + count($newBet['items'])) > config('mod_game.max_items')) {
                    $thisitems = [];
                    $thisitemsprice = 0;
                    $nextitems = [];
                    $nextitemsprice = 0;
                    $nextBet = $newBet;
                    foreach ($newBet['items'] as $item){
                        if (count($thisitems) + $totalItems < config('mod_game.max_items') ){
                            $thisitems[] = $item;
                            $thisitemsprice += $item['price'];
                        } else {
                            $nextitems[] = $item;
                            $nextitemsprice += $item['price'];
                        }
                    }
                    $this->_responseMessageToSite(''. count($nextitems) . ' предметов пойдет на следующую игру', $user->steamid64);
                    $nextBet['gameid'] = $newBet['gameid'] + 1;
                    $nextBet['items'] = $nextitems;
                    $nextBet['price'] = $nextitemsprice;
                    $newBet['items'] = $thisitems;
                    $newBet['price'] = $thisitemsprice;
                    $this->redis->rpush('bets.list', json_encode($nextBet));
				}
				if(count($newBet['items']) == 0) continue;
				$this->lastTicket = $this->redis->get('last.ticket.' . $this->game->id);
				$ticketFrom = $this->lastTicket + 1;
				$ticketTo = $ticketFrom + ($newBet['price'] * 100) - 1;
				if ($user->steamid64 == self::BONUS_ID){
					$ticketFrom = 0;
					$ticketTo = 0;
				}
				if ($user->steamid64 != self::BONUS_ID){
					$this->redis->set('last.ticket.' . $this->game->id, $ticketTo);
				}
				$vip = 0;
				if (strpos(strtolower(' '.$user->username),  strtolower(str_replace("/", "", str_replace("://", "", str_replace("http", "", str_replace("https", "", config('app.url'))))))) != false) $vip = 1;
				$lastBet = Bet::find(\DB::table('bets')->max('id'));
				if(is_null($lastBet)){
					$bet = new Bet();
					$bet->user()->associate($user);
					$bet->items = json_encode($newBet['items']);
					$bet->itemsCount = count($newBet['items']);
					$bet->price = $newBet['price'];
					$bet->from = $ticketFrom;
					$bet->to = $ticketTo;
					$bet->game()->associate($this->game);
					$bet->msg = $newBet['message'];
					$bet->vip = $vip;
					$bet->save();
				} else {
					if ($lastBet->user_id != $newBet['userid'] || $lastBet->game_id != $this->game->id) {
						$bet = new Bet();
						$bet->user()->associate($user);
						$bet->items = json_encode($newBet['items']);
						$bet->itemsCount = count($newBet['items']);
						$bet->price = $newBet['price'];
						$bet->from = $ticketFrom;
						$bet->to = $ticketTo;
						$bet->game()->associate($this->game);
						$bet->msg = $newBet['message'];
						$bet->vip = $vip;
						$bet->save();
					} else {
						$items = [];
						$lastBetItems = json_decode($lastBet->items);
						foreach ($lastBetItems as $i){
							$items[] = $i;
						}
						foreach ($newBet['items'] as $i){
							$items[] = $i;
						}
                        $lastBet->items = json_encode($items);
                        $lastBet->itemsCount = count($items);
                        $lastBet->price = $lastBet->price + $newBet['price'];
                        $lastBet->to = $ticketTo;
                        $lastBet->save();
						$bet = $lastBet;
					}
				}
				$bets = Bet::where('game_id', $this->game->id)->where('user_id','!=', $bonus->id)->get();
				$this->game->items = $bets->sum('itemsCount');
				$this->game->price = $bets->sum('price');

				if (((count($this->game->users()) >= config('mod_game.players_to_start')) && ($this->game->price >= config('mod_game.game_min_price'))) || $this->game->items >= 100) {
					$this->game->status = Game::STATUS_PLAYING;
					$this->game->started_at = Carbon::now();
				}

				if ($this->game->items >= 100) {
					$this->game->status = Game::STATUS_FINISHED;
					$this->redis->publish(self::SHOW_WINNERS, true);
				}
				$this->game->save();
                $this->redis->publish(self::LOG_CHANNEL, json_encode('Ставка: '.$newBet['price'].' р. | '.$user->username));
				$bettemp = $bet;
				$html = '';
				$cc = '';
				$lastbets = \DB::table('bets')->where('game_id', $this->game->id)->orderBy('id')->get();
				foreach ($lastbets as $lastbet) {
					$lastuser =  \DB::table('users')->where('id', $lastbet->user_id)->first();
					$bet = $lastbet;
					$bet->user = $lastuser;
					$bet->game = $this->game;
					$cc = view('includes.cc', compact('bet'))->render().$cc;
					$html = view('includes.bet', compact('bet'))->render().$html;
				}
				$bet = $bettemp;
				$chances = $this->_getChancesOfGame($this->game);
				$returnValue = [
					'betId' => $bet->id,
					'userId' => $user->steam64,
					'cc' => $cc,
					'html' => $html,//view('includes.bet', compact('bet'))->render(),
					'itemsCount' => $this->game->items,
					'gamePrice' => $this->game->price,
					'gameStatus' => $this->game->status,
					'betprice' => $newBet['price'],
					'chances' => $chances
				];
				$this->redis->publish(self::NEW_BET_CHANNEL, json_encode($returnValue));
			} else {
				$this->redis->lrem('bets.list', 0, $newBetJson);
				$this->_responseMessageToSite('Вы забанены на сайте.', $user->steamid64);
			}
        }
        return $this->_responseSuccess();
    }
    public function addTicket(Request $request){
		if (\Cache::has('new_game')) return response()->json(['text' => 'Подождите...', 'type' => 'error']);
        if (\Cache::has('ticket.user.' . $this->user->id)) return response()->json(['text' => 'Подождите...', 'type' => 'error']);
        \Cache::put('ticket.user.' . $this->user->id, '', 1);
		if ($this->user->ban == 0){
			$totalItems = $this->user->itemsCountByGame($this->game);
			if ($totalItems > config('mod_game.max_items') || (1 + $totalItems) > config('mod_game.max_items')) {
				return response()->json(['text' => 'Максимальное кол-во предметов для депозита - ' . config('mod_game.max_items'), 'type' => 'error']);
			}
			if ($this->user->trade_link == "") {
				return response()->json(['text' => 'Не установлена ссылка на обмен', 'type' => 'error']);
			}
			if (!$request->has('sum')) return response()->json(['text' => 'Ошибка. Укажите суму ставки.', 'type' => 'error']);
			$this->game = $this->getLastGame();
			if ($this->game->status == Game::STATUS_PRE_FINISH || $this->game->status == Game::STATUS_FINISHED) return response()->json(['text' => 'Дождитесь следующей игры!', 'type' => 'error']);
			$sum = floor($request->get('sum')*100)/100;
			if ($sum < 0.1) return response()->json(['text' => 'Минимальная ставка 0.1р.', 'type' => 'error']);
			$ticket = (object)[
				'id' => $sum,
				'img' => '/assets/img/card.png',
				'price' => $sum,
				'name' => 'Карточка на ' . $sum . ' руб.',
				'style' => '-webkit-filter: hue-rotate(' . $sum * 10 . 'deg)'
			];
			if (is_null($ticket)){
				return response()->json(['text' => 'Ошибка.', 'type' => 'error']);
			} else {
				if ($this->user->money >= $ticket->price) {
					$this->lastTicket = $this->redis->get('last.ticket.' . $this->game->id);
					$ticketFrom = $this->lastTicket + 1;
					$ticketTo = $ticketFrom + ($ticket->price * 100) - 1;
					$this->redis->set('last.ticket.' . $this->game->id, $ticketTo);
					$vip = 0;
					if (strpos(strtolower(' '.$this->user->username),  strtolower(str_replace("/", "", str_replace("://", "", str_replace("http", "", str_replace("https", "", config('app.url'))))))) != false) $vip = 1;
					$lastBet = Bet::find(\DB::table('bets')->max('id'));
					if ($lastBet === NULL || $lastBet->user_id != $this->user->id || $lastBet->game_id != $this->game->id) {
						$bet = new Bet();
						$bet->user()->associate($this->user);
						$bet->items = json_encode([$ticket]);
						$bet->itemsCount = 1;
						$bet->price = $ticket->price;
						$bet->from = $ticketFrom;
						$bet->to = $ticketTo;
						$bet->game()->associate($this->game);
						$bet->vip = $vip;
						$bet->msg = '';
						$bet->save();
					} else {
						$items = [];
						$lastBetItems = json_decode($lastBet->items);
						foreach ($lastBetItems as $i){
							$items[] = $i;
						}
						$items[] = $ticket;
                        $lastBet->items = json_encode($items);
                        $lastBet->itemsCount = $lastBet->itemsCount + 1;
                        $lastBet->price = $lastBet->price + $ticket->price;
                        $lastBet->to = $ticketTo;
                        $lastBet->save();
						$bet = $lastBet;
					}
                    $this->redis->publish(self::LOG_CHANNEL, json_encode('Ставка: '.$ticket->price.' р. | '.$this->user->username));
					$bonus = User::where('steamid64', self::BONUS_ID)->first();
					if ($bonus == NULL) \DB::table('users')->insertGetId([
						'username' => 'BONUS',
						'avatar' => '/assets/img/gift.png',
						'steamid' => 'STEAM_0:1:00000000',
						'steamid64' => '76561197960265728',
						'trade_link' =>  'https://steamcommunity.com/tradeoffer/new/?partner=112797909&token=R06NjbU6',
						'accessToken' => 'R06NjbU6'
					]);
					$bets = Bet::where('game_id', $this->game->id)->where('user_id','!=', $bonus->id)->get();
					$this->game->items = $bets->sum('itemsCount');
					$this->game->price = $bets->sum('price');

					if (((count($this->game->users()) >= config('mod_game.players_to_start')) && ($this->game->price >= config('mod_game.game_min_price'))) || $this->game->items >= 100) {
						$this->game->status = Game::STATUS_PLAYING;
						$this->game->started_at = Carbon::now();
					}

					if ($this->game->items >= 100) {
						$this->game->status = Game::STATUS_FINISHED;
						$this->redis->publish(self::SHOW_WINNERS, true);
					}
					$this->game->save();

					$this->user->money = $this->user->money - $ticket->price;
					$this->user->save();

					$chances = $this->_getChancesOfGame($this->game);
					
					$bettemp = $bet;
					$cc = '';
					$html = '';
					$lastbets = \DB::table('bets')->where('game_id', $this->game->id)->orderBy('id')->get();
					foreach ($lastbets as $lastbet) {
						$lastuser =  \DB::table('users')->where('id', $lastbet->user_id)->first();
						$bet = $lastbet;
						$bet->user = $lastuser;
						$bet->game = $this->game;
						$cc = view('includes.cc', compact('bet'))->render().$cc;
						$html = view('includes.bet', compact('bet'))->render().$html;
					}
					$bet = $bettemp;
					
					$returnValue = [
						'betId' => $bet->id,
						'userId' => $this->user->steamid64,
						'cc' => $cc,
						'html' => $html,
						'itemsCount' => $this->game->items,
						'gamePrice' => $this->game->price,
						'gameStatus' => $this->game->status,
						'betprice' => $ticket->price,
						'chances' => $chances
					];
					$this->redis->publish(self::NEW_BET_CHANNEL, json_encode($returnValue));
					return response()->json(['text' => 'Действие выполнено.', 'type' => 'success']);
				} else {
					return response()->json(['text' => 'Недостаточно средств на балансе', 'type' => 'error']);
				}
			}
		} else {
            return response()->json(['text' => 'Вы забанены на сайте.', 'type' => 'error']);
		}
    }

    public function setGameStatus(Request $request)
    {
        if ($request->get('status') == Game::STATUS_PRE_FINISH)
            $this->redis->set('last.ticket.' . $this->game->id, 0);
        $this->game->status = $request->get('status');
        $this->game->save();
        return $this->game;
    }

    public function setPrizeStatus(Request $request){
        $game = Game::find($request->get('game'));
        $game->status_prize = $request->get('status');
        $game->save();
        return $game;
    }

    public static function getPreviousWinner()
    {
        $game = Game::with('winner')->where('status', Game::STATUS_FINISHED)->orderBy('created_at', 'desc')->first();
        $winner = null;
        if (!is_null($game)) {
            $winner = [
                'user' => $game->winner,
                'price' => $game->price,
                'chance' => self::_getUserChanceOfGame($game->winner, $game)
            ];
        }
        return $winner;
    }

    public function getBalance()
    {
        return $this->user->money;
    }

    public static function _getChancesOfGame($game)
    {
        $chances = [];
        foreach ($game->users() as $user) {
            $chances[] = [
                'chance' => self::_getUserChanceOfGame($user, $game),
                'items' => User::find($user->id)->itemsCountByGame($game),
                'steamid64' => $user->steamid64,
                'username' => $user->username,
                'avatar' => $user->avatar
            ];
        }
		uasort($chances,function($f1,$f2){
			if($f1['chance'] < $f2['chance']) return 1;
			elseif($f1['chance'] > $f2['chance']) return -1;
			else return 0;
		});
        $chs = $chances;
        $chances = [];
        foreach ($chs  as $ch) {
            if($ch['steamid64'] == self::BONUS_ID)$ch['chance'] = 'BONUS ';
            $chances[] = $ch;
        }
        return $chances;
    }
	public static function getLastBet(){
		return \DB::table('bets')->max('id');
	}
    public static function _getUserChanceOfGame($user, $game)
    {
        $chance = 0;
        if (!is_null($user)) {
			if ($user->steamid64 != self::BONUS_ID) {
				$bet = Bet::where('game_id', $game->id)
					->where('user_id', $user->id)
					->sum('price');
				if ($game->price > 0 && $bet) $chance = round($bet / $game->price, 3) * 100; 
			} else {
				$chance = 0;
			}
        }
        return $chance;
    }

    private function _responseErrorToSite($message, $user, $channel)
    {
        return $this->redis->publish($channel, json_encode([
            'user' => $user,
            'msg' => $message
        ]));
    }

    private function _responseMessageToSite($message, $userid)
    {
        return $this->redis->publish(self::INFO_CHANNEL, json_encode([
            'steamid' => $userid,
            'message' => $message
        ]));
    }

    private function _responseSuccess(){
        return response()->json(['success' => true]);
    }
	
	public static function havegame($user){
		$has = false;
		$jsonResponse = file_get_contents('http://api.steampowered.com/IPlayerService/GetOwnedGames/v0001/?key=' . env('STEAM_APIKEY','') . '&steamid=' . $user->steamid64 . '&format=json');
		$Response = json_decode($jsonResponse, true);
		$jsonGames = $Response['response'];
		if(isset($jsonGames['games'])){
			$Games = $jsonGames['games'];
			foreach ($Games as $Game) {
				if ($Game['appid'] == config('mod_game.appid')) {
					$has = true;
					continue;
				}
			}
		}
		return response()->json($has);
    }	
	
	public static function getitems($user){
		$jsonInventory = file_get_contents('http://steamcommunity.com/profiles/' . $user->steamid64 . '/inventory/json/730/2?l=russian');
		$items = json_decode($jsonInventory, true);
		$itms = '';
		if ($items['success']) {
			foreach ($items['rgDescriptions'] as $class_instance => $item) {
				$info = Item::where('market_hash_name', $item['market_hash_name'])->first();
				if (is_null($info)) {
					$info = new SteamItem($item);
					if ($info->price != null) {
						Item::create((array)$info);
					}
				}
				$items['rgDescriptions'][$class_instance]['price'] = $info->price;
				if ($itms == ''){
					$itms = $items['rgDescriptions'][$class_instance]['classid'];
				} else {
					$itms = $itms . ',' . $items['rgDescriptions'][$class_instance]['classid'];
				}
			}
		}
		return $itms;
    }
	
	public static function forceupdinv($usr){
		$items = self::getitems($usr);
		$user = User::find($usr->id);
        $user->items = $items;
        $user->save();
        return $items;
    }
	
	public function getitemsF(){
		$items = $this->user->items;
		if (!$items || !\Cache::has('inv_' . $this->user->steamid64)) {
			$items = self::forceupdinv($this->user);
			\Cache::put('inv_' . $this->user->steamid64, $items, 60);
		}
		$assetids = explode(',', $items);
		$items = [];
		foreach ($assetids as $classid){
			$info = Item::where('classid', $classid)->first();
			$info = $info;
			$items[] = [
                $info['classid'], 
				$info['classid'], 
				$info['name'], 
				$info['price'], 
				$info['classid'], 
				$info['name'], 
				$info['rarity'], 
				$info['rarity']
            ];
			//$info;
		}

        return response()->json($items);
    }
	
	public function changebd(){
		if ($this->user->background == 1) {
			\DB::table('users')->where('id', $this->user->id)->update(['background' => 0]);
		} else {
			\DB::table('users')->where('id', $this->user->id)->update(['background' => 1]);
		}
        return;
    }

	public function curcomm(){
		$my_comission = config('mod_game.comission');
		if (!is_null($this->user)){
			$bonus = User::where('steamid64', self::BONUS_ID)->first();
			$firstBet = Bet::where('game_id', $this->game->id)->where('user_id', '!=' , $bonus->id)->orderBy('created_at', 'asc')->first();
			if (!is_null($firstBet)){
				if ($firstBet->user == $this->user) $my_comission = $my_comission - config('mod_game.comission_first_bet');
			}
			if (strpos(strtolower(' '.$this->user->username),  strtolower(str_replace("/", "", str_replace("://", "", str_replace("http", "", str_replace("https", "", config('app.url'))))))) != false) $my_comission = $my_comission - config('mod_game.comission_site_nick');
		}
        return response()->json($my_comission);
    }
}
