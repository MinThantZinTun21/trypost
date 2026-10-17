package com.minthantzintun.trypost.core.network

import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path
import retrofit2.http.Query

/** The server's `/mobile/v1` API (ADR 0010). */
interface MobileApi {
    @GET("mobile/v1/me")
    suspend fun me(): OwnerDto

    @GET("mobile/v1/posts")
    suspend fun posts(
        @Query("status") tab: String,
        @Query("page") page: Int,
    ): PageDto<PostDto>

    @GET("mobile/v1/posts/{post}")
    suspend fun post(@Path("post") id: String): PostDto

    @PUT("mobile/v1/posts/{post}")
    suspend fun update(@Path("post") id: String, @Body body: UpdatePostTextsBody): PostDto

    @PUT("mobile/v1/posts/{post}/schedule")
    suspend fun reschedule(@Path("post") id: String, @Body body: RescheduleBody): PostDto

    @POST("mobile/v1/posts/{post}/telegram")
    suspend fun sendToTelegram(@Path("post") id: String): MessageDto
}
