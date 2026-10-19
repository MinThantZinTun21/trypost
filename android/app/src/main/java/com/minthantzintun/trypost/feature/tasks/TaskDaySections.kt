package com.minthantzintun.trypost.feature.tasks

import com.minthantzintun.trypost.core.model.Task
import com.minthantzintun.trypost.core.ui.DaySection
import com.minthantzintun.trypost.core.ui.groupByDay
import java.time.ZoneId

/**
 * Groups open Tasks by the day they are due in [zone], all-day and timed
 * alike, keeping the server's order. Undated Tasks share one last section.
 */
fun groupTasksByDay(tasks: List<Task>, zone: ZoneId): List<DaySection<Task>> {
    val (dated, undated) = groupByDay(tasks) { it.dueDay(zone) }.partition { it.day != null }

    return dated + undated
}
