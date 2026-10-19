# TryPost (minimal fork)

A personal scheduler that publishes one Owner's posts to Facebook Pages, TikTok and YouTube at a chosen time.

## Language

**Owner**:
The single person who uses this install. They are created by the seeder and cannot sign up through the app.
_Avoid_: User (in prose), member, customer, admin

**Platform**:
One of the three networks this app publishes to: Facebook, TikTok or YouTube.
_Avoid_: Network, provider, channel

**Social account**:
A connected Facebook Page, TikTok account or YouTube channel that posts are published to.
_Avoid_: Connection, profile, integration

**Post**:
A piece of content with media that the Owner writes once and sends to one or more social accounts. A Post exists only once it is scheduled or going out; there are no Drafts.
_Avoid_: Update, content item

**Post platform**:
A Post's entry for one social account. It holds that account's publishing status and outcome.
_Avoid_: Target, destination

**Scheduled post**:
A Post with a set publish time that has not started publishing yet.
_Avoid_: Queued post, pending post

**Reschedule**:
Move a Scheduled post to a new publish time. The time must be in the future.
_Avoid_: Move, postpone, edit time

**Post now**:
Publish a Scheduled post right away instead of at its publish time, through the same path as the composer's Post now.
_Avoid_: Publish immediately, force publish

**Content type**:
The form a Post takes on one Platform: a Facebook Post, Reel or Story; a TikTok Video or Photo; or a YouTube Short.
_Avoid_: Format, post kind

**Notification**:
An in-app message telling the Owner that a Post failed to publish, a Social account was disconnected, or an account used by an upcoming Post needs reconnecting.
_Avoid_: Alert, email

**Title**:
The headline a Platform shows with a video or photo: a YouTube Short's title, a Facebook Reel's title or a TikTok Photo's title. Set per Post platform; an empty YouTube title falls back to the first sentence of the Post's content.
_Avoid_: Heading, subject

**Description**:
The longer text a Platform shows under a YouTube Short, a Facebook Reel or a TikTok Photo. Set per Post platform; empty means the Post's content.
_Avoid_: Body, summary

**TikTok caption**:
Text that replaces the Post's content for one TikTok account's Video, so a single Post can say something different on TikTok.
_Avoid_: TikTok title

**Tags**:
Keywords a YouTube Short carries to help people find it. Set per Post platform.
_Avoid_: Hashtags, keywords

**Assistant**:
An AI tool, such as Claude Code, connected to the app over MCP that reads Social accounts and creates Posts on the Owner's behalf.
_Avoid_: Bot, agent, API client

**Mobile app**:
The Owner's Android app. It views Posts, edits their texts, Reschedules them, Posts them now and Sends them to Telegram, and it uploads Videos; it never creates Posts or changes a Post's media.
_Avoid_: Client, API client

**Mobile token**:
The secret one phone gets when the Owner signs in to the Mobile app with their web email and password. The server keeps only its hash; Sign out on that phone, or a change of the Owner's password, ends it.
_Avoid_: API key, MOBILE_TOKEN, session

**Insights**:
Numbers Facebook reports about how a Facebook Page and its posts perform. Facebook only; TikTok and YouTube have none in this app.
_Avoid_: Analytics, stats, metrics (in prose)

**Page insights**:
Insights about one Social account's Facebook Page as a whole: its followers, how many times and to how many people its content was shown, and how people engaged with it.
_Avoid_: Account analytics, page stats

**Post insights**:
Insights about one Post platform that published to a Facebook Page: views, reach, reactions and clicks, plus video views, watch time and Reel plays for videos. Facebook does not give this app comment or share counts.
_Avoid_: Post analytics, post stats

**Insights snapshot**:
The Page insights the app stored for one day, so the dashboard shows history without asking Facebook each time.
_Avoid_: Cache, sync

**TikTok handoff**:
How a TikTok Post platform publishes when TikTok delivery is Telegram: at its scheduled time the app sends its video (or photos) and then its TikTok caption to the Owner's one Telegram channel, and the Owner posts it on TikTok by hand. The Post platform then counts as Published, with no live link. Telegram is not a Platform; nothing else is sent there automatically.
_Avoid_: Telegram mirror, Telegram platform, Telegram account, cross-post

**Download link**:
A permanent link in a TikTok handoff that downloads a Post's video file without signing in.
_Avoid_: Share link, public link

**Send to Telegram**:
The Owner's manual action that sends one Post's media and then its text to the Owner's Telegram channel right away, the way a TikTok handoff does. It does not change the Post's status, and it can be repeated.
_Avoid_: Telegram mirror, share, forward

**Video**:
A video file the Owner uploads from the Mobile app, with a name, for the Assistant to fetch. It is not part of any Post and has no Platform texts.
_Avoid_: Library item, asset, clip, media
