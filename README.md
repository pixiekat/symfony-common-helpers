# Symfony Common Helpers

This is a collection of common traits and files for Symfony applications to be reused across systems.

## Configuration

Everything is defaulted, so the bundle works with no configuration at all. The
one knob that matters:

```yaml
# config/packages/symfony_helpers.yaml
symfony_helpers:
    user_class: 'App\Entity\User'      # default
    resolve_target_entities: true      # default
```

Bundle entities never name `App\Entity\User` — a bundle cannot reference a class
that only exists inside the consuming app, which is what used to force you to
re-create `ResetPasswordRequest` by hand in every project. Instead they map
against `Pixiekat\SymfonyHelpers\Interfaces\Entity\HelpersUserInterface`, and the
bundle prepends the Doctrine config that resolves that interface to `user_class`
for you. Your side of the bargain is one declaration:

```php
class User extends BaseEntity implements HelpersUserInterface { /* unchanged */ }
```

`HelpersUserInterface` extends Symfony's `UserInterface` and adds `getId()`,
which `EntityIdTrait` already provides — so any entity built with these traits
satisfies it as-is.

To use the reset-password flow, point SymfonyCasts at the bundle's repository:

```yaml
# config/packages/reset_password.yaml
symfonycasts_reset_password:
    request_password_repository: Pixiekat\SymfonyHelpers\Repository\ResetPasswordRequestRepository
```

Register the bundle's migrations so `doctrine:migrations:migrate` picks them up:

```yaml
# config/packages/doctrine_migrations.yaml
doctrine_migrations:
    migrations_paths:
        'Pixiekat\SymfonyHelpers': '%kernel.project_dir%/vendor/pixiekat/symfony-common-helpers/migrations'
```

## Styles

The bundle ships **one** stylesheet: `Resources/public/styles/symfony-common-helpers.css`.

**Control panel pages need nothing.** `admin/cp_layout.html.twig` links it itself,
so every admin screen is styled with no configuration in the consuming app.

**Public-facing widgets** — `place_block()`, `place_shoutbox()` — are rendered
inside *your* templates, so your base template links it, once:

```twig
{% block stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset('bundles/pixiekatsymfonyhelpers/styles/symfony-common-helpers.css') }}">
{% endblock %}
```

Symfony has no bundle-level asset attachment — there is no `attach_library()`
equivalent, because Twig streams output top-to-bottom and `</head>` is already
written by the time a body partial runs. One explicit line is the mechanism, and
it is the better outcome anyway: the `<link>` lands in `<head>` so nothing
flashes unstyled, and there is exactly one place to look when styles go missing.

The stylesheet sets **structure only** — spacing, stacking, wrapping — and leaves
colour and type to you, so dropping a widget into a site never fights that site's
design. Everything tunable is a custom property, so override by redeclaring them
rather than by out-specifying selectors:

```css
.shoutbox { --shoutbox-gap: 1rem; }
```

### Accessibility utilities

The same stylesheet also carries `.visually-hidden` — content that stays in the
accessibility tree while being invisible on screen. Use it for skip links, for
`aria-live` regions, and to give repeated controls a distinguishable accessible
name (a column of thirty identical "Edit" buttons is unusable in a screen
reader):

```twig
<a class="skip-link visually-hidden" href="#main">Skip to content</a>

<a href="{{ path('...', {id: item.id}) }}">
    Edit<span class="visually-hidden"> {{ item.name }}</span>
</a>
```

It is **not** a synonym for `display: none`. That, and `visibility: hidden`,
remove the element from the accessibility tree altogether — a live region hidden
that way announces nothing, and a skip link hidden that way cannot be focused.

Two consequences of the "link it once, yourself" rule above are worth stating,
because both surface as puzzling bugs rather than as obviously missing styles:

* **A live region created by app-level JavaScript needs this stylesheet on
  *every* page, not only the ones using a widget from this bundle.** A Turbo
  Drive announcer is the usual case. Without the link, the region renders as
  visible text in the middle of the page.
* **Control panel pages already have it** via `admin-cp.css`, which declares its
  own copy so the layout stays self-contained. Change one declaration and you
  must change both.

Assets resolve through AssetMapper automatically (the bundle's `Resources/public`
is mapped to `bundles/pixiekatsymfonyhelpers`), and `assets:install` also copies
them into `public/bundles/` for apps not using AssetMapper — so `asset()` works
either way.

## Blocks

A **block** is a named chunk of page content. A **block item** is one entry
inside it — normally a link. Together they replace the hand-written arrays that
accumulate in a `SidebarManager`-style service.

There is deliberately no "region" concept. Placement lives in your templates,
where you can grep for it:

```twig
{{ place_block('social_links') }}
{{ place_block('meta_links', { show_title: false }) }}
{{ place_block('friends', { limit: 5, heading_level: 3 }) }}
```

...or in a controller, through the same service:

```php
public function index(BlockManager $blocks): Response {
    return $this->render('page.html.twig', [
        'sidebar' => $blocks->render('social_links'),
    ]);
}
```

If you want the data rather than the markup, take the entity and write your own:

```twig
{% set social = get_block('social_links') %}
{% if social %}
    {% for item in social.enabledItems %}
        <a href="{{ item.url }}">{{ item.label }}</a>
    {% endfor %}
{% endif %}
```

### Render options

| Option | Default | What it does |
| --- | --- | --- |
| `show_title` | `true` | Renders the block's label as a heading. When `false` the heading stays in the DOM but is visually hidden, so the document outline and the `aria-labelledby` reference survive. |
| `heading_level` | `2` | Heading rank for the title, clamped to 1–6. Configurable because the correct rank depends on where you placed the block, and skipping levels is an accessibility failure. |
| `limit` | `null` | Render at most N items. |
| `template` | `null` | Use a different Twig template for this call only. |
| `vars` | `[]` | Extra variables passed straight through to the template. |

A block that does not exist, or is disabled, renders as an empty string and logs
a warning. A template referencing a not-yet-seeded block should leave a hole in
the page, not a 500.

### Storing content

A block holds either a prose `body`, an ordered list of items, or both.
Presentation quirks that belong to one site rather than to the general idea of
"a link" go in the `flags` JSON bag, so adding one never needs a migration:

```php
$block = (new Block('social_links'))
    ->setLabel('Socials');

$block->addItem(
    (new BlockItem('bluesky_personal'))
        ->setLabel('spacecadetgrrl.me')
        ->setUrl('https://bsky.app/profile/spacecadetgrrl.me')
        ->setWeight(50)
);

$entityManager->persist($block);   // cascades to items
$entityManager->flush();
```

Flags read by the default item template: `wrapper_label` (text shown before the
item), `new_window` (off by default — see below), `rel` (extra rel tokens).

### Overriding the markup

Set `template` on a block, or pass it per call. A replacement receives exactly
the same variables as the default (`block`, `items`, `show_title`,
`heading_level`, `options`), so it is a drop-in.

The default templates assume one thing from your stylesheet: a
`.visually-hidden` class using the standard clip-rect pattern.

### Accessibility notes

* Blocks render as a `<section>` labelled by their own heading via
  `aria-labelledby`. A `<section>` with no accessible name is ignored by screen
  readers, so the label is what makes it a real landmark.
* An item with no URL renders as a `<span>`, not a dead `<a href="">`. An anchor
  with no destination is focusable, announced as a link, and does nothing.
* `new_window` defaults to **false** even though forcing `target="_blank"` on
  outbound links is common. Opening a new window without warning fails WCAG
  3.2.5 and breaks the Back button. When you do opt in, the template appends a
  visually hidden "(opens in a new window)" and applies
  `rel="noopener noreferrer"`.

## Shoutbox

Drop it anywhere, the same way you place a block:

```twig
{{ place_shoutbox() }}
{{ place_shoutbox('staff', { limit: 10, show_title: false, heading_level: 3 }) }}
{% for shout in shoutbox_latest('default', 5) %}...{% endfor %}
```

There is also a standalone page at `/shoutbox/{channel}`, which is itself just a
`place_shoutbox()` call — so the embedded widget and the full page can never
drift apart.

One `shouts` table serves every shoutbox; which box a message belongs to is the
`channel` column. Channels are implicit: posting to one creates it.

**Graceful degradation.** Ordinary form POST, then a redirect (post/redirect/get).
No JavaScript anywhere — it works in Lynx, with scripts off, and a refresh after
posting re-renders instead of re-submitting. Turbo layers on top without needing
any change here.

**Abuse handling** lives in `ShoutboxManager`, not in the controller:

* Bans go through the existing `BanManager`, which already understands literal
  addresses and /8, /16 and /24 prefixes.
* Flood control is a `COUNT` over the shouts table (5 per minute per IP by
  default) rather than a new dependency on symfony/rate-limiter. It survives a
  cache flush, needs no app configuration, and swapping it out later means
  changing one method.
* Refusals raise `ShoutRejectedException`, whose message is already written for
  a visitor to read.

**Who may post** is one line in `ShoutboxVoter` — `SHOUT_POST` is granted to
everyone including anonymous visitors by default. Return `$this->isAuthenticated()`
instead to make it members-only, and every entry point respects it.

If you run behind a reverse proxy, configure Symfony's trusted proxies. Otherwise
every shout records the proxy's address and the flood limit applies to your whole
site at once.

## Admin

Blocks and the shoutbox both have full CRUD under `/admincp`, built on the same
pattern as the existing taxonomy screens:

| Section | Path | Permission |
| --- | --- | --- |
| Control panel index | `/admincp` | `AdminVoterInterface::ADMIN_ADMINISTER` |
| Blocks | `/admincp/blocks` | `BlockVoterInterface::BLOCK_ADMINISTER` |
| Block items | `/admincp/blocks/{block}/items` | `BlockVoterInterface::BLOCK_ITEM_*` |
| Shoutbox | `/admincp/shoutbox` | `ShoutboxVoterInterface::SHOUTBOX_ADMINISTER` |

`admin_layout.html.twig` now renders a section nav and the flash messages around
every admin screen.

**Admin templates must override `admin_content`, not `body`.** The layout claims
`body` in order to provide the nav and flashes; a template that overrides `body`
replaces all of it. That is occasionally what you want, and never what you want
by accident.

Nav entries are gated by the same voter attribute that guards the route they
point at, so nobody is offered a link to a 403.

The shoutbox moderation queue shows every status, including the spam and
soft-deleted rows the public view hides. Status changes are POSTs with CSRF
tokens rather than links — a state-changing GET can be fired by a prefetcher, an
`<img>` on a hostile page, or a mail client preview.

### Deciding who is an admin

This package does **not** decide `AdminVoterInterface::ADMIN_ADMINISTER`, the
attribute guarding `/admincp`. What counts as "an admin" is application-specific,
so `Pixiekat\SymfonyHelpers\Security\Voter\AdminVoter` denies it and leaves the
answer to your app. Until you supply one, admin routes answer 403 — the package
fails closed rather than leaving the control panel open to anyone logged in.

To supply one, extend the interface and implement the voter:

```php
// src/Interfaces/Security/Voter/AdminVoterInterface.php
namespace App\Interfaces\Security\Voter;

use Pixiekat\SymfonyHelpers\Interfaces\Security\Voter\AdminVoterInterface as HelpersAdminVoterInterface;

interface AdminVoterInterface extends HelpersAdminVoterInterface {
  public const PERMISSION_CAN_ACCESS_REPORTS = 'can_access_reports';
  // …whatever else your app gates on
}
```

```php
// src/Security/Voter/AdminVoter.php
namespace App\Security\Voter;

use App\Interfaces\Security\Voter\AdminVoterInterface;
use Pixiekat\SymfonyHelpers\Security\Voter\BaseVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminVoter extends BaseVoter implements AdminVoterInterface {

  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, $this->getAttributes(), true);
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();
    if (!$user instanceof UserInterface) {
      return false;
    }

    return match ($attribute) {
      self::ADMIN_ADMINISTER => $this->hasAtLeastOneAdminRole($user, $this->getAdminRoles()),
      self::PERMISSION_CAN_ACCESS_REPORTS => $this->hasGlobalAdminRole($user, $this->getAdminRoles()),
      default => false,
    };
  }

  /**
   * The roles this app treats as administrative. Every name needs the ROLE_
   * prefix — see the note below.
   */
  private function getAdminRoles(): array {
    return ['ROLE_SUPER_ADMIN', 'ROLE_SYSADMIN', 'ROLE_ADMIN', 'ROLE_DATA_STEWARD'];
  }
}
```

Autoconfigure tags it as a voter; no service wiring needed. `BaseVoter` gives you
`hasGlobalAdminRole()` (the `ROLE_*_ADMIN` set only), `hasAtLeastOneAdminRole()`
(that set plus whatever you pass in), `isSysAdmin()`, `isAuthenticated()` and
`isFirstUser()` to build the decision out of.

**`extends HelpersAdminVoterInterface` is load-bearing, not tidiness.**
`BaseVoter::getAttributes()` builds its supported-attribute list by reflecting
over the constants visible on the voter class — that is, constants reachable
through its parents and its interfaces. Referencing `ADMIN_ADMINISTER` by its
fully-qualified name inside `voteOnAttribute()` does nothing for that list. Skip
the `extends` and your `supports()` returns false for `administer admin`, the
voter abstains on every admin route, and the match arm you wrote is unreachable
code that never runs and never errors.

Naming it as `self::ADMIN_ADMINISTER` rather than by FQCN is worth doing for the
same reason: `self::` only resolves if the constant really is inherited, which is
the same condition `getAttributes()` tests. Break one and you break the other,
loudly, instead of getting a voter that silently abstains.

Both voters vote. Under Symfony's default `affirmative` strategy this package
votes DENIED and yours votes GRANTED, so yours decides. If you have changed
`access_decision_manager.strategy` to `unanimous`, this package's DENIED will
veto yours — set `strategy: affirmative`, or give your voter a higher priority
and use `priority` strategy.

**Custom roles must start with `ROLE_`.** Symfony's `RoleVoter` skips any
attribute without that prefix, so `isGranted('DATA_STEWARD')` is not false, it is
unanswerable: the voter abstains and, with nothing else granting it, the result
is a 403 with no explanation anywhere. `role_hierarchy` will expand an unprefixed
role perfectly happily and the profiler will list it among a user's reachable
roles, which makes this a genuinely nasty afternoon. `hasAtLeastOneAdminRole()`
and `hasGlobalAdminRole()` call `isGranted()` on every name you hand them, so an
unprefixed entry in your `getAdminRoles()` is silently ignored.

## Contact

I'm on the following places.

* [Bluesky](https://bsky.app/profile/netkitten.net)
* [Codeberg](https://codeberg.org/pixiekat)
* [Mastodon](https://tech.lgbt/@pixiekat)
