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
A piece of content with media that the Owner writes once and sends to one or more social accounts.
_Avoid_: Update, content item

**Post platform**:
A Post's entry for one social account. It holds that account's publishing status and outcome.
_Avoid_: Target, destination

**Scheduled post**:
A Post with a set publish time that has not started publishing yet.
_Avoid_: Queued post, pending post

**Draft**:
A Post with no publish time that is not yet scheduled.

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

**Assistant**:
An AI tool, such as Claude Code, connected to the app over MCP that reads Social accounts and creates Posts on the Owner's behalf.
_Avoid_: Bot, agent, API client
