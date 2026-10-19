package com.minthantzintun.trypost.core.network

import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Builds a [MobileApi] for a server address and Mobile token. The address is
 * the Owner's choice, so there is no fixed Retrofit base URL. Without a token
 * it can only sign in.
 */
@Singleton
class MobileApiFactory @Inject constructor(
    private val client: OkHttpClient,
    private val json: Json,
) {
    fun create(serverUrl: String, token: String? = null): MobileApi {
        val authenticated = client.newBuilder()
            .addInterceptor { chain ->
                val request = chain.request().newBuilder().header("Accept", "application/json")

                if (token != null) {
                    request.header("Authorization", "Bearer $token")
                }

                chain.proceed(request.build())
            }
            .build()

        return Retrofit.Builder()
            .baseUrl("${serverUrl.trimEnd('/')}/")
            .client(authenticated)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
            .create(MobileApi::class.java)
    }
}
