<?php declare(strict_types=1);

namespace TomasVotruba\Website\TweetPublisher\Application;

use Symfony\Component\Console\Style\SymfonyStyle;
use TomasVotruba\Website\TweetPublisher\Tweet\Tweet;
use TomasVotruba\Website\TweetPublisher\TweetProvider\PostTweetsProvider;
use TomasVotruba\Website\TweetPublisher\TweetProvider\UnpublishedTweetsResolver;
use TomasVotruba\Website\TweetPublisher\TwitterApi\TwitterApiWrapper;

/**
 * @inspire https://gist.github.com/petrvacha/28ec8f5eac39283f1e7dce350f5a65ad
 * @thanks Petr Vacha
 */
final class TweetPublisherApplication
{
    /**
     * @var int
     */
    private $minimalGapInDays;

    /**
     * @var TwitterApiWrapper
     */
    private $twitterApiWrapper;

    /**
     * @var SymfonyStyle
     */
    private $symfonyStyle;

    /**
     * @var UnpublishedTweetsResolver
     */
    private $unpublishedTweetsResolver;

    /**
     * @var PostTweetsProvider
     */
    private $postTweetsProvider;

    public function __construct(
        int $minimalGapInDays,
        TwitterApiWrapper $twitterApiWrapper,
        PostTweetsProvider $postTweetsProvider,
        UnpublishedTweetsResolver $unpublishedTweetsResolver,
        SymfonyStyle $symfonyStyle
    ) {
        $this->minimalGapInDays = $minimalGapInDays;
        $this->twitterApiWrapper = $twitterApiWrapper;
        $this->postTweetsProvider = $postTweetsProvider;
        $this->unpublishedTweetsResolver = $unpublishedTweetsResolver;
        $this->symfonyStyle = $symfonyStyle;
    }

    public function run(): void
    {
        if (! $this->isRunAllowed()) {
            return;
        }

        $tweetsToPublish = $this->unpublishedTweetsResolver->excludePublishedTweets(
            $this->postTweetsProvider->provide(),
            $this->twitterApiWrapper->getPublishedTweets()
        );

        if (! count($tweetsToPublish)) {
            $this->symfonyStyle->warning(
                'There is no new tweet to publish. Add a new one to one of your post under "tweet:" option.'
            );
            return;
        }

        $tweet = $this->getMostRecentTweet($tweetsToPublish);

        $this->tweet($tweet);

        $this->symfonyStyle->success(sprintf('Tweet "%s" was successfully published.', $tweet->getText()));
    }

    private function isRunAllowed(): bool
    {
        $daysSinceLastTweet = $this->twitterApiWrapper->getDaysSinceLastTweet();
        if ($daysSinceLastTweet >= $this->minimalGapInDays) {
            return true;
        }

        $this->symfonyStyle->warning(sprintf(
            'Only %d days passed since last tweet. Minimal gap is %d days, so no tweet until then.',
            $daysSinceLastTweet,
            $this->minimalGapInDays
        ));

        return false;
    }

    private function tweet(Tweet $tweet): void
    {
        if ($tweet->getImage()) {
            $this->twitterApiWrapper->publishTweetWithImage($tweet->getText(), $tweet->getImage());
        } else {
            $this->twitterApiWrapper->publishTweet($tweet->getText());
        }
    }

    /**
     * @param Tweet[] $tweetsToPublish
     */
    private function getMostRecentTweet(array $tweetsToPublish): Tweet
    {
        return array_shift($tweetsToPublish);
    }
}
