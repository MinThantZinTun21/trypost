package com.minthantzintun.trypost.core.model

/** A Social account the post list can be filtered to. */
data class SocialAccount(
    val id: String,
    val platform: Platform,
    val label: String,
    val isConnected: Boolean,
)
