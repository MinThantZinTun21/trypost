package com.minthantzintun.trypost.core.session

/**
 * What the app keeps after signing in: the server it talks to, the Owner's
 * `MOBILE_TOKEN`, and the Owner's name for the top bar.
 */
data class Session(
    val serverUrl: String,
    val token: String,
    val ownerName: String,
)
