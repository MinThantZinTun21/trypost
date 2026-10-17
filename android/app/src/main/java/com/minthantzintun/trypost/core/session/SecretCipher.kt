package com.minthantzintun.trypost.core.session

/** Encrypts the values the [SessionStore] writes to disk. */
interface SecretCipher {
    fun encrypt(plainText: String): String

    /** Returns `null` when the value can no longer be read, e.g. its key is gone. */
    fun decrypt(cipherText: String): String?
}
