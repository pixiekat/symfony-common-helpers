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

## Contact

I'm on the following places.

* [Bluesky](https://bsky.app/profile/netkitten.net)
* [Codeberg](https://codeberg.org/pixiekat)
* [Mastodon](https://tech.lgbt/@pixiekat)
