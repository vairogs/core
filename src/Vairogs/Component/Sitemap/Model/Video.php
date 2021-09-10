<?php declare(strict_types = 1);

namespace Vairogs\Component\Sitemap\Model;

use DateTimeInterface;
use Vairogs\Component\Sitemap\Model\Traits\VideoGetters;
use Vairogs\Component\Sitemap\Model\Traits\VideoSetters;

class Video
{
    protected string $thumbnailLoc;
    protected string $title;
    protected string $description;
    protected array $tags = [];
    protected array $restrictions = [];
    protected array $platforms = [];
    protected ?string $contentLoc = null;
    protected ?string $playerLoc = null;
    protected ?int $duration = null;
    protected ?DateTimeInterface $expirationDate = null;
    protected ?float $rating = null;
    protected ?int $viewCount = null;
    protected ?DateTimeInterface $publicationDate = null;
    protected ?string $familyFriendly = null;
    protected ?string $category = null;
    protected ?string $galleryLoc = null;
    protected ?string $requiresSubscription = null;
    protected ?string $uploader = null;
    protected ?string $live = null;

    use VideoGetters;
    use VideoSetters;
}
