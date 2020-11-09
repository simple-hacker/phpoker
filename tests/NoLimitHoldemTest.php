<?php

namespace simplehacker\PHPoker\Tests;

use simplehacker\PHPoker\Card;
use simplehacker\PHPoker\Player;
use simplehacker\PHPoker\Games\NoLimitHoldem;
use simplehacker\PHPoker\Tests\PHPokerTestCase;
use simplehacker\PHPoker\Exceptions\HandException;
use simplehacker\PHPoker\Exceptions\InvalidActionException;

class NoLimitHoldemTest extends PHPokerTestCase
{
    /** @test */
    public function must_give_an_array_of_players_when_instantiating()
    {
        $this->expectException(HandException::class);

        $players = [new Player(), 'Not a player', new Player()];

        $hand = new NoLimitHoldem($players);
    }

    /** @test */
    public function there_must_be_at_least_two_players_in_the_hand()
    {
        $this->expectException(HandException::class);

        $players = [new Player()];

        $hand = new NoLimitHoldem($players);
    }

    /** @test */
    public function cannnot_exceed_maximum_number_of_players()
    {
        $this->expectException(HandException::class);

        // Create 11 Players
        $players = $this->createPlayers(11);

        $hand = new NoLimitHoldem($players);
    }

    /** @test */
    public function can_get_the_players_of_the_hand()
    {
        // Create 8 Players
        $players = $this->createPlayers(8);
        $hand = new NoLimitHoldem($players);

        $this->assertSame(8, count($hand->getPlayers()));
    }

    /** @test */
    public function can_deal_hand_limit_cards_to_players()
    {
        // No limit Holdem can only have two cards each.

        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        $hand->deal();

        $players = $hand->getPlayers();

        foreach($players as $player) {
            $this->assertSame(2, $player->getHoleCardsCount());
        }
    }

    /** @test */
    public function cannot_deal_again_if_hand_has_already_been_dealt()
    {
        $this->expectException(InvalidActionException::class);

        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        $hand->deal();

        // Try to deal hand again
        $hand->deal();
    }

    /** @test */
    public function a_flop_can_be_dealt()
    {
        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        $hand->deal();

        $hand->flop();

        $this->assertCount(3, $hand->getCommunityCards());
    }

    /** @test */
    public function a_flop_cannot_be_dealt_if_player_deal_has_not_been_completed()
    {
        $this->expectException(InvalidActionException::class);

        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        // Do not deal to players

        $hand->flop();
    }

    /** @test */
    public function cannot_deal_flop_twice_in_a_row()
    {
        $this->expectException(InvalidActionException::class);

        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        $hand->deal();
        $hand->flop();
        $hand->flop();
    }

    /** @test */
    public function a_turn_can_be_dealt_if_flop_has_been_dealt()
    {
        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        $hand->deal();
        $hand->flop();

        $hand->turn();

        $this->assertCount(4, $hand->getCommunityCards());
    }

    /** @test */
    public function a_turn_cannot_be_dealt_if_there_are_not_three_community_cards()
    {
        $this->expectException(InvalidActionException::class);

        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        $hand->deal();
        // Do not deal flop
        $hand->turn();
    }

    /** @test */
    public function cannot_deal_turn_twice_in_a_row()
    {
        $this->expectException(InvalidActionException::class);

        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        $hand->deal();
        $hand->flop();
        $hand->turn();
        $hand->turn();
    }

    /** @test */
    public function a_river_can_be_dealt_if_flop_and_turn_have_been_dealt()
    {
        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        $hand->deal();
        $hand->flop();
        $hand->turn();

        $hand->river();

        $this->assertCount(5, $hand->getCommunityCards());
    }

    /** @test */
    public function a_river_cannot_be_dealt_if_there_are_not_four_community_cards()
    {
        $this->expectException(InvalidActionException::class);

        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        $hand->deal();
        $hand->flop();
        // Do not deal turn
        $hand->river();
    }

    /** @test */
    public function cannot_deal_river_twice_in_a_row()
    {
        $this->expectException(InvalidActionException::class);

        $players = $this->createPlayers(4);

        $hand = new NoLimitHoldem($players);

        $hand->deal();
        $hand->flop();
        $hand->turn();
        $hand->river();
        $hand->river();
    }

    /** @test */
    public function can_set_community_cards_and_players_hole_cards_for_testing_only()
    {
        $communityCards = ['Ah', '3s', 'Kd', '9d', 'Qs']; 
        $playersWithHoleCards = [['5s', '5d'], ['9c', 'Tc']];

        // TestCase helper method for setting communityCards and player's hole cards
        // using RelectionClass to set protected properties
        // Used for testing hand winners are correct, because otherwise it would be random every test
        $hand = $this->createNoLimitHoldemHand($communityCards, $playersWithHoleCards);

        $mappedCommunityCards = array_map(fn($card) => new Card($card), $communityCards);
        $mappedPlayers = array_map(function($cards) {
            $player = new Player();
            $holeCards = array_map(fn($card) => new Card($card), $cards);
            $player->giveCards($holeCards);
            return $player;
        }, $playersWithHoleCards);

        $this->assertEquals($mappedCommunityCards, $hand->getCommunityCards());
        $this->assertEquals($mappedPlayers, $hand->getPlayers());
    }

    /**
     * @test
     * @dataProvider winners
     * */
    public function can_get_correct_winners_of_the_hand($communityCards, $players, $expectedWinnersKeys)
    {
        $hand = $this->createNoLimitHoldemHand($communityCards, $players);

        $allPlayers = $hand->getPlayers();
        $winners = [];

        foreach($expectedWinnersKeys as $playerKey) {
            $winners[$playerKey] = $allPlayers[$playerKey];
        }

        $this->assertEquals($winners, $hand->getWinners());
    }

    public function winners()
    {
        // [Array of community card values], [Array of players which holds an array of hole card values], [Keys of players expected to win]
        return [
            [['Ah', '3s', 'Kd', '9d', 'Qs'], [['4s', '5c'], ['9s', 'Td'], ['5s', '5d'], ['9c', 'Tc']], [1, 3]], // Players 1 and 3 = AKQJT straight
        ];
    }

    /** @test */
    public function cannot_get_winner_if_there_is_not_five_commmunity_cards()
    {
        $this->expectException(InvalidActionException::class);

        // Only four communityCards
        $communityCards = ['Ah', '3s', 'Kd', '9d'];
        $players = [['4s', '5c'], ['9s', 'Td'], ['5s', '5d'], ['9c', 'Tc']];

        $hand = $this->createNoLimitHoldemHand($communityCards, $players);

        $hand->getWinners();
    }
}